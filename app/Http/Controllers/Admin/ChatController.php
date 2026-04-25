<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatSupportAssignment;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    public function index(Request $request): Response
    {
        $query = Conversation::with([
            'userA:id,first_name,last_name,email,profile_picture,role,last_seen_at',
            'userB:id,first_name,last_name,email,profile_picture,role,last_seen_at',
            'creator:id,first_name,last_name,profile_picture,role',
            'participants.user:id,first_name,last_name,profile_picture,role',
            'show:id,name,event_day_id',
            'show.eventDay:id,event_id,label',
            'show.eventDay.event:id,name',
            'lastMessage',
        ])->withCount(['messages', 'participants']);

        if ($request->filled('event')) {
            $query->whereHas('show.eventDay.event', fn($q) => $q->where('events.id', $request->event));
        }

        if ($request->filled('context_type')) {
            if ($request->context_type === 'general') {
                $query->whereNull('context_type')->where('is_group', false);
            } elseif ($request->context_type === 'group') {
                $query->where('is_group', true);
            } else {
                $query->where('context_type', $request->context_type);
            }
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('userA', fn($uq) => $uq->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%"))
                  ->orWhereHas('userB', fn($uq) => $uq->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%"));
            });
        }

        $conversations = $query->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        // Users for new chat modal
        $users = User::whereNotIn('role', ['admin'])
            ->select('id', 'first_name', 'last_name', 'email', 'role', 'status')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('Admin/Chats/Index', [
            'conversations' => $conversations,
            'events'        => $events,
            'users'         => $users,
            'filters'       => $request->only(['event', 'search', 'context_type']),
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $conversation->load([
            'userA:id,first_name,last_name,email,profile_picture,role,last_seen_at',
            'userB:id,first_name,last_name,email,profile_picture,role,last_seen_at',
            'creator:id,first_name,last_name,profile_picture,role',
            'participants.user:id,first_name,last_name,profile_picture,role',
            'show:id,name,event_day_id',
            'show.eventDay:id,event_id,label,date',
            'show.eventDay.event:id,name',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,first_name,last_name,profile_picture')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Admin/Chats/Show', [
            'conversation' => $conversation,
            'messages'     => $messages,
        ]);
    }

    /**
     * Start a new conversation from the panel.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:5000',
        ]);

        $sender = auth()->user();
        $targetId = $request->user_id;

        if ($sender->id === $targetId) {
            return back()->withErrors(['user_id' => 'You cannot chat with yourself.']);
        }

        $conversation = $this->chatService->findOrCreateConversation($sender->id, $targetId);

        // Send initial message if provided
        if ($request->filled('message')) {
            $this->chatService->sendMessage($conversation, $sender, $request->message);
        }

        return redirect()->route('admin.chats.show', $conversation)
            ->with('success', 'Conversation started.');
    }

    /**
     * Send a message. Only participants of the conversation can post — internal
     * roles (admin, operation, …) cannot write in conversations that don't belong
     * to them. If they need to reach a user, they should start their own chat.
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $sender = auth()->user();

        if (!$conversation->hasParticipant($sender->id)) {
            abort(403, 'You cannot write in a conversation you are not part of.');
        }

        $this->chatService->sendMessage($conversation, $sender, $request->body);

        return back();
    }

    /**
     * List the support assignments (which operation users handle which roles)
     * + the active operation users available to pick from.
     */
    public function supportAssignments(): JsonResponse
    {
        $assignments = ChatSupportAssignment::with('user:id,first_name,last_name,status')
            ->get()
            ->groupBy('role')
            ->map(fn ($rows) => $rows->map(fn ($a) => [
                'user_id'     => $a->user_id,
                'first_name'  => $a->user?->first_name,
                'last_name'   => $a->user?->last_name,
                'status'      => $a->user?->status,
                'inactive'    => $a->user?->status !== 'active',
            ])->values());

        $agents = User::where('role', 'operation')
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return response()->json([
            'roles'       => ['designer', 'model', 'media', 'volunteer'],
            'assignments' => $assignments,
            'agents'      => $agents,
        ]);
    }

    /**
     * Replace the full mapping. Body: { designer: [ids], model: [ids], ... }.
     */
    public function saveSupportAssignments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'designer'   => 'array',
            'designer.*' => 'integer|exists:users,id',
            'model'      => 'array',
            'model.*'    => 'integer|exists:users,id',
            'media'      => 'array',
            'media.*'    => 'integer|exists:users,id',
            'volunteer'  => 'array',
            'volunteer.*' => 'integer|exists:users,id',
        ]);

        DB::transaction(function () use ($data) {
            foreach (['designer', 'model', 'media', 'volunteer'] as $role) {
                $userIds = $data[$role] ?? [];
                ChatSupportAssignment::where('role', $role)->delete();
                foreach ($userIds as $userId) {
                    ChatSupportAssignment::create(['role' => $role, 'user_id' => $userId]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Create a group from the panel. Internal team can create groups with any
     * users; the form already filters to non-admin users.
     */
    public function createGroup(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:120',
            'member_ids'   => 'required|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $sender = auth()->user();
        if (!$sender->isInternalTeam()) abort(403);

        $conversation = $this->chatService->createGroup($sender, $request->name, $request->member_ids);

        return redirect()->route('admin.chats.show', $conversation)
            ->with('success', 'Group created.');
    }

    /**
     * Reassign a support conversation to another operation user.
     */
    public function reassign(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $actor = auth()->user();
        if (!$actor->isAdmin() && !$actor->isOperation()) {
            abort(403);
        }

        $newAgent = User::find($request->user_id);
        if (!$newAgent->isInternalTeam()) {
            return response()->json(['message' => 'The new agent must be internal staff.'], 422);
        }

        try {
            $this->chatService->reassignSupportConversation($conversation, $newAgent, $actor);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
