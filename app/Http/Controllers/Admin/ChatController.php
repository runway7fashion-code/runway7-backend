<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    public function index(Request $request): Response
    {
        $query = Conversation::with([
            'userA:id,first_name,last_name,email,profile_picture,role',
            'userB:id,first_name,last_name,email,profile_picture,role',
            'show:id,name,event_day_id',
            'show.eventDay:id,event_id,label',
            'show.eventDay.event:id,name',
            'lastMessage',
        ])->withCount('messages');

        if ($request->filled('event')) {
            $query->whereHas('show.eventDay.event', fn($q) => $q->where('events.id', $request->event));
        }

        if ($request->filled('context_type')) {
            if ($request->context_type === 'general') {
                $query->whereNull('context_type');
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
        $users = User::whereNull('deleted_at')
            ->whereNotIn('role', ['admin'])
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
            'userA:id,first_name,last_name,email,profile_picture,role',
            'userB:id,first_name,last_name,email,profile_picture,role',
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
     * Send a message from the panel (no longer read-only).
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $sender = auth()->user();

        // Admin/internal users can send in any conversation
        if (!$sender->isInternalTeam() && !$conversation->hasParticipant($sender->id)) {
            abort(403);
        }

        // If admin is not a participant, they join as user_a or user_b
        if (!$conversation->hasParticipant($sender->id)) {
            // Admin is observing — send as system message instead
            $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body'      => $request->body,
                'type'      => 'text',
            ]);
            $conversation->update(['last_message_at' => now()]);
        } else {
            $this->chatService->sendMessage($conversation, $sender, $request->body);
        }

        return back();
    }
}
