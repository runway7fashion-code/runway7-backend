<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;
        $channels = [new PrivateChannel('conversation.' . $conversation->id)];
        foreach ($conversation->participantIds() as $uid) {
            $channels[] = new PrivateChannel('user.' . $uid);
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'NewMessage';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $sender->first_name . ' ' . $sender->last_name,
            'sender'          => [
                'id'              => $sender->id,
                'first_name'      => $sender->first_name,
                'last_name'       => $sender->last_name,
                'profile_picture' => $sender->profile_picture,
            ],
            'body'            => $this->message->body,
            'type'            => $this->message->type,
            'image_url'       => $this->message->image_url,
            'is_read'         => false,
            'read_at'         => null,
            'delivered_at'    => null,
            'created_at'      => $this->message->created_at->toISOString(),
        ];
    }
}
