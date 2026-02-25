<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Conversation::with([
            'model:id,first_name,last_name,email,profile_picture',
            'designer:id,first_name,last_name,email,profile_picture',
            'show:id,name,event_day_id',
            'show.eventDay:id,event_id,label',
            'show.eventDay.event:id,name',
            'lastMessage',
        ])->withCount('messages');

        if ($request->filled('event')) {
            $query->whereHas('show.eventDay.event', fn($q) => $q->where('events.id', $request->event));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('model', fn($mq) => $mq->where('first_name', 'ilike', "%{$request->search}%")->orWhere('last_name', 'ilike', "%{$request->search}%"))
                  ->orWhereHas('designer', fn($dq) => $dq->where('first_name', 'ilike', "%{$request->search}%")->orWhere('last_name', 'ilike', "%{$request->search}%"));
            });
        }

        $conversations = $query->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'name']);

        return Inertia::render('Admin/Chats/Index', [
            'conversations' => $conversations,
            'events'        => $events,
            'filters'       => $request->only(['event', 'search']),
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $conversation->load([
            'model:id,first_name,last_name,email,profile_picture,role',
            'designer:id,first_name,last_name,email,profile_picture,role',
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
}
