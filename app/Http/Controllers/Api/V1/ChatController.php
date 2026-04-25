<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\Show;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * List all conversations for the authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all');
        $search = $request->input('search');

        $conversations = $this->chatService->getConversationsForUser($user, $filter, $search);

        $data = $conversations->map(function (Conversation $c) use ($user) {
            $result = [
                'id'              => $c->id,
                'status'          => $c->status,
                'context_type'    => $c->context_type,
                'is_group'        => (bool) $c->is_group,
                'last_message'    => $c->lastMessage ? [
                    'body'       => $c->lastMessage->body,
                    'type'       => $c->lastMessage->type,
                    'sender_id'  => $c->lastMessage->sender_id,
                    'created_at' => $c->lastMessage->created_at->toISOString(),
                ] : null,
                'unread_count'    => $c->unreadCountFor($user->id),
                'last_message_at' => $c->last_message_at?->toISOString(),
                'is_archived'     => !is_null($c->my_archived_at ?? null),
                'is_favorited'    => !is_null($c->my_favorited_at ?? null),
                'is_pinned'       => !is_null($c->my_pinned_at ?? null),
                'is_muted'        => !is_null($c->my_muted_until ?? null) && \Carbon\Carbon::parse($c->my_muted_until)->isFuture(),
                'muted_until'     => $c->my_muted_until ? \Carbon\Carbon::parse($c->my_muted_until)->toISOString() : null,
            ];

            if ($c->is_group) {
                $c->loadMissing('participants:id,conversation_id,user_id');
                $result['name']          = $c->name;
                $result['member_count']  = $c->participants->count();
                $result['created_by_id'] = $c->created_by_id;
                $result['other_participant'] = null;
            } else {
                $other = $c->getOtherParticipant($user->id);
                $result['other_participant'] = $other ? [
                    'id'              => $other->id,
                    'name'            => $other->full_name,
                    'profile_picture' => $other->profile_picture,
                    'role'            => $other->role,
                    'phone'           => in_array($other->role, User::ROLES_PARTICIPANT) ? $other->phone : null,
                    'is_online'       => $other->is_online,
                    'last_seen_at'    => $other->last_seen_at?->toISOString(),
                ] : null;
            }

            // Include show info for casting conversations
            if ($c->show) {
                $result['show'] = [
                    'id'   => $c->show->id,
                    'name' => $c->show->name,
                ];
            }

            return $result;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Start (or resume) a general conversation with Operations support.
     * Only participants (model, designer, media, volunteer) can initiate.
     * Reuses existing conversation if one already exists.
     */
    public function startSupportChat(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowedRoles = ['model', 'designer', 'media', 'volunteer'];
        if (!in_array($user->role, $allowedRoles)) {
            return response()->json(['message' => 'Only participants can start support chats.'], 403);
        }

        $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        // Route to the agent assigned to this requester's role (balanced),
        // falling back to any active operation user, then admin.
        $operationUser = $this->chatService->resolveSupportAgent($user);

        if (!$operationUser) {
            return response()->json(['message' => 'No support agents available.'], 503);
        }

        $conversation = Conversation::findOrCreateBetween($user->id, $operationUser->id);

        // Send initial message if provided
        if ($request->filled('message')) {
            $this->chatService->sendMessage($conversation, $user, $request->message);
            $conversation->refresh();
        }

        $other = $conversation->getOtherParticipant($user->id);

        return response()->json([
            'conversation' => [
                'id'     => $conversation->id,
                'status' => $conversation->status,
                'context_type' => $conversation->context_type,
                'other_participant' => [
                    'id'              => $other->id,
                    'name'            => $other->full_name,
                    'profile_picture' => $other->profile_picture,
                    'role'            => $other->role,
                    'phone'           => in_array($other->role, User::ROLES_PARTICIPANT) ? $other->phone : null,
                    'is_online'       => $other->is_online,
                    'last_seen_at'    => $other->last_seen_at?->toISOString(),
                ],
                'last_message_at' => $conversation->last_message_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get messages for a conversation in chronological order (oldest → newest).
     *
     * Optional query params:
     *   - limit=N (default: all messages)
     *   - before=<message_id> to page backward for scroll-up history
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $query = $conversation->messages()
            ->with('sender:id,first_name,last_name,profile_picture');

        if ($before = $request->input('before')) {
            $query->where('id', '<', $before);
        }

        $limit = (int) $request->input('limit', 0);

        // Take the most recent N (DESC) then reverse so the client gets oldest→newest
        if ($limit > 0) {
            $messages = $query->orderByDesc('created_at')->limit($limit)->get()->reverse()->values();
        } else {
            $messages = $query->orderBy('created_at')->get();
        }

        return response()->json(['data' => $messages]);
    }

    /**
     * Send a message.
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'body'      => 'required_without:image_url|string|max:2000',
            'type'      => 'in:text,image',
            'image_url' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        try {
            $message = $this->chatService->sendMessage(
                $conversation,
                $user,
                $request->input('body', ''),
                $request->input('type', 'text'),
                $request->input('image_url'),
            );

            return response()->json([
                'data' => [
                    'id'              => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id'       => $message->sender_id,
                    'body'            => $message->body,
                    'type'            => $message->type,
                    'image_url'       => $message->image_url,
                    'is_read'         => false,
                    'read_at'         => null,
                    'delivered_at'    => null,
                    'created_at'      => $message->created_at->toISOString(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Mark the authenticated user as actively viewing a conversation.
     * Used by SendChatMessageNotificationJob to suppress push/sms/in-app
     * notifications while the recipient already has the chat open.
     */
    public function focus(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $user->forceFill([
            'active_conversation_id' => $conversation->id,
            'active_conversation_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Clear the active conversation when the user leaves the chat screen.
     */
    public function blur(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'active_conversation_id' => null,
            'active_conversation_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Emit a "typing" realtime event to the other participant. No DB write.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $isTyping = (bool) $request->input('is_typing', true);

        try {
            broadcast(new UserTyping($conversation, $user, $isTyping))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('UserTyping broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────── GROUPS (Phase B) ───────────────────────────────────────

    /**
     * Create a group. Two flows depending on caller role:
     *
     *   internal team → free-form { name, member_ids[] }
     *   designer      → { show_id, name, member_ids[] } where each member must be a
     *                   model confirmed in show_model for that show with this designer.
     *                   At most one group per (designer, show).
     */
    public function createGroup(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'name'         => 'required|string|max:120',
            'member_ids'   => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
        ];

        if ($user->role === 'designer') {
            $rules['show_id'] = 'required|integer|exists:shows,id';
        } elseif (!$user->isInternalTeam()) {
            return response()->json(['message' => 'Only designers and internal staff can create groups.'], 403);
        }

        $data = $request->validate($rules);

        if ($user->role === 'designer') {
            $error = $this->validateDesignerGroup($user, (int) $data['show_id'], $data['member_ids']);
            if ($error) return response()->json(['message' => $error], 422);
        }

        $conversation = $this->chatService->createGroup(
            $user,
            $data['name'],
            $data['member_ids'],
            $request->input('show_id')
        );

        return response()->json($this->serializeGroup($conversation, $user), 201);
    }

    /**
     * Group detail: name, creator, members.
     */
    public function showGroup(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureGroupAccess($request->user(), $conversation);

        $conversation->load(['creator:id,first_name,last_name,profile_picture',
            'participants.user:id,first_name,last_name,profile_picture,role']);

        return response()->json($this->serializeGroup($conversation, $request->user()));
    }

    public function updateGroup(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureGroupCreator($request->user(), $conversation);
        $request->validate(['name' => 'required|string|max:120']);
        $conversation->update(['name' => $request->name]);
        return response()->json($this->serializeGroup($conversation->fresh(), $request->user()));
    }

    public function addGroupMember(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureGroupCreator($request->user(), $conversation);
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $member = User::find($request->user_id);
        if ($conversation->show_id && $request->user()->role === 'designer') {
            $error = $this->validateDesignerGroup($request->user(), $conversation->show_id, [$member->id]);
            if ($error) return response()->json(['message' => $error], 422);
        }

        $this->chatService->addMember($conversation, $member);
        return response()->json($this->serializeGroup($conversation->fresh(), $request->user()));
    }

    public function removeGroupMember(Request $request, Conversation $conversation, int $userId): JsonResponse
    {
        $this->ensureGroupCreator($request->user(), $conversation);
        if ($userId === $conversation->created_by_id) {
            return response()->json(['message' => 'The creator cannot be removed. Delete or transfer the group first.'], 422);
        }
        $this->chatService->removeMember($conversation, $userId);
        return response()->json(['ok' => true]);
    }

    /**
     * Any active participant can leave the group.
     */
    public function leaveGroup(Request $request, Conversation $conversation): JsonResponse
    {
        if (!$conversation->is_group) abort(422, 'Not a group.');
        if (!$conversation->hasParticipant($request->user()->id)) abort(403);
        $this->chatService->removeMember($conversation, $request->user()->id);
        return response()->json(['ok' => true]);
    }

    /**
     * For the designer chat-list "create group" flow: returns shows where the
     * designer is assigned AND has at least one confirmed model AND hasn't
     * created a group yet.
     */
    public function eligibleShowsForGroup(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'designer') abort(403);

        $existingShowIds = Conversation::where('is_group', true)
            ->where('created_by_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('show_id')
            ->pluck('show_id')
            ->all();

        $shows = Show::with('eventDay.event:id,name')
            ->whereHas('designers', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('models', fn ($q) => $q->where('show_model.designer_id', $user->id)
                ->where('show_model.status', 'confirmed'))
            ->whereNotIn('id', $existingShowIds)
            ->get(['id', 'name', 'event_day_id']);

        return response()->json([
            'data' => $shows->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'event_name'  => $s->eventDay?->event?->name,
                'event_date'  => $s->eventDay?->date?->format('Y-m-d'),
            ])->values(),
        ]);
    }

    /**
     * Returns the models the designer can include in a group for the given show
     * (those confirmed in show_model for that designer + show).
     */
    public function eligibleMembersForGroup(Request $request, Show $show): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'designer') abort(403);
        if (!$show->designers()->where('users.id', $user->id)->exists()) abort(403);

        $models = $show->models()
            ->wherePivot('designer_id', $user->id)
            ->wherePivot('status', 'confirmed')
            ->get(['users.id', 'first_name', 'last_name', 'profile_picture']);

        return response()->json(['data' => $models]);
    }

    /**
     * Groups where both the authenticated user and {user} are active members.
     * Used by the mobile member-info screen.
     */
    public function commonGroups(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        $groups = Conversation::where('is_group', true)
            ->where('status', 'active')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $me->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->withCount('participants')
            ->with('show:id,name')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'data' => $groups->map(fn ($g) => [
                'id'              => $g->id,
                'name'            => $g->name,
                'member_count'    => $g->participants_count,
                'show_id'         => $g->show_id,
                'show_name'       => $g->show?->name,
                'last_message_at' => $g->last_message_at?->toISOString(),
            ])->values(),
        ]);
    }

    private function ensureGroupAccess(User $user, Conversation $conversation): void
    {
        if (!$conversation->is_group) abort(422, 'Not a group.');
        if (!$conversation->hasParticipant($user->id) && !$user->isInternalTeam()) abort(403);
    }

    private function ensureGroupCreator(User $user, Conversation $conversation): void
    {
        if (!$conversation->is_group) abort(422, 'Not a group.');
        if ($conversation->created_by_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'Only the creator can manage this group.');
        }
    }

    /**
     * Returns null if the designer is allowed to create/expand a group for the
     * given show with the given member ids; otherwise an error message.
     */
    private function validateDesignerGroup(User $designer, int $showId, array $memberIds): ?string
    {
        $show = Show::find($showId);
        if (!$show) return 'Show not found.';

        if (!$show->designers()->where('users.id', $designer->id)->exists()) {
            return 'You are not assigned to this show.';
        }

        // 1 group per (designer, show).
        $existing = Conversation::where('is_group', true)
            ->where('created_by_id', $designer->id)
            ->where('show_id', $showId)
            ->where('status', 'active')
            ->exists();
        if ($existing) return 'You already have a group for this show.';

        // All members must be confirmed models for this designer in this show.
        $confirmedIds = DB::table('show_model')
            ->where('show_id', $showId)
            ->where('designer_id', $designer->id)
            ->where('status', 'confirmed')
            ->pluck('model_id')
            ->all();

        $invalid = array_diff($memberIds, $confirmedIds);
        if (!empty($invalid)) {
            return 'All members must be models you confirmed for this show.';
        }

        return null;
    }

    private function serializeGroup(Conversation $g, User $viewer): array
    {
        $g->loadMissing([
            'creator:id,first_name,last_name,profile_picture',
            'participants.user:id,first_name,last_name,profile_picture,role',
            'show:id,name,event_day_id',
            'show.eventDay:id,event_id',
            'show.eventDay.event:id,name',
        ]);

        $event = $g->show?->eventDay?->event;

        return [
            'id'              => $g->id,
            'is_group'        => true,
            'name'            => $g->name,
            'show_id'         => $g->show_id,
            'show_name'       => $g->show?->name,
            'event_id'        => $event?->id,
            'event_name'      => $event?->name,
            'created_by_id'   => $g->created_by_id,
            'is_creator'      => $g->created_by_id === $viewer->id,
            'creator'         => $g->creator ? [
                'id'              => $g->creator->id,
                'name'            => trim($g->creator->first_name.' '.$g->creator->last_name),
                'profile_picture' => $g->creator->profile_picture,
            ] : null,
            'members' => $g->participants->map(fn ($p) => [
                'user_id'         => $p->user_id,
                'name'            => trim(($p->user->first_name ?? '').' '.($p->user->last_name ?? '')),
                'profile_picture' => $p->user?->profile_picture,
                'role'            => $p->role,
                'role_label'      => $p->user?->role,
                'joined_at'       => $p->joined_at?->toISOString(),
            ])->values(),
            'last_message_at' => $g->last_message_at?->toISOString(),
            'created_at'      => $g->created_at?->toISOString(),
        ];
    }

    /**
     * Mute a conversation for the authenticated user. Body: { duration: '8h'|'1w'|'forever' }.
     * While muted, the user does not receive push or in-app notifications for new
     * messages in this conversation. The other participants are not affected.
     */
    public function mute(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['duration' => 'required|in:8h,1w,forever']);

        $user = $request->user();
        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $until = match ($request->input('duration')) {
            '8h'      => now()->addHours(8),
            '1w'      => now()->addWeek(),
            'forever' => \App\Models\ConversationUserState::MUTE_FOREVER,
        };

        $state = ConversationUserState::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id], []
        );
        $state->muted_until = $until;
        $state->save();

        return response()->json([
            'is_muted'    => true,
            'muted_until' => optional($state->muted_until)->toISOString(),
        ]);
    }

    public function unmute(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $state = ConversationUserState::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id], []
        );
        $state->muted_until = null;
        $state->save();

        return response()->json(['is_muted' => false, 'muted_until' => null]);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse   { return $this->setState($request, $conversation, 'archived', true); }
    public function unarchive(Request $request, Conversation $conversation): JsonResponse { return $this->setState($request, $conversation, 'archived', false); }
    public function favorite(Request $request, Conversation $conversation): JsonResponse  { return $this->setState($request, $conversation, 'favorited', true); }
    public function unfavorite(Request $request, Conversation $conversation): JsonResponse{ return $this->setState($request, $conversation, 'favorited', false); }
    public function pin(Request $request, Conversation $conversation): JsonResponse       { return $this->setState($request, $conversation, 'pinned', true); }
    public function unpin(Request $request, Conversation $conversation): JsonResponse     { return $this->setState($request, $conversation, 'pinned', false); }

    /**
     * Toggle the per-user state of a conversation: archived/favorited/pinned.
     */
    private function setState(Request $request, Conversation $conversation, string $flag, bool $set): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $column = $flag . '_at';
        $state = ConversationUserState::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            []
        );
        $state->{$column} = $set ? now() : null;
        $state->save();

        return response()->json([
            'is_archived'  => !is_null($state->archived_at),
            'is_favorited' => !is_null($state->favorited_at),
            'is_pinned'    => !is_null($state->pinned_at),
        ]);
    }

    /**
     * Mark messages as delivered (called when device receives the push / WS event).
     */
    public function markAsDelivered(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $count = $this->chatService->markAsDelivered($conversation, $user);

        return response()->json(['delivered_count' => $count]);
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user->id)) {
            abort(403, 'You do not have access to this conversation.');
        }

        $count = $this->chatService->markAsRead($conversation, $user);

        return response()->json(['read_count' => $count]);
    }
}
