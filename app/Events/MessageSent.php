<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $recipientId;
    public $senderId;

    /**
     * Create a new event instance.
     *
     * @param array $message
     * @param int $recipientId
     * @param int $senderId
     * @return void
     */
    public function __construct(array $message, int $recipientId, int $senderId)
    {
        $this->message = $message;
        $this->recipientId = $recipientId;
        $this->senderId = $senderId;

        Log::info("MessageSent event created", [
            'recipient_id' => $recipientId,
            'sender_id' => $senderId,
            'message_id' => $message['id'] ?? 'unknown'
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            'messages.' . $this->recipientId,
            'messages.' . $this->senderId
        ];
        Log::info("Broadcasting message to channels: " . implode(', ', $channels));

        return [
            new PrivateChannel('messages.' . $this->recipientId),
            new PrivateChannel('messages.' . $this->senderId), // Also send to sender for confirmation
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }
}
