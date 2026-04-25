<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagesRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public User $reader,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('conversation.' . $this->conversation->id)];
        foreach ($this->conversation->participantIds() as $uid) {
            $channels[] = new PrivateChannel('user.' . $uid);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessagesRead';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'reader_id'       => $this->reader->id,
            'read_at'         => now()->toISOString(),
        ];
    }
}
