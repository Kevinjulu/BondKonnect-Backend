<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public $userId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $notification, int $userId)
    {
        $this->notification = $notification;
        $this->userId = $userId;

        Log::info('NotificationSent event created', [
            'user_id' => $userId,
            'notification_id' => $notification['id'] ?? 'unknown',
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channel = 'notifications.'.$this->userId;
        Log::info("Broadcasting notification to channel: {$channel}");

        return [
            new PrivateChannel($channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'timestamp' => now()->toISOString(),
        ];
    }
}
