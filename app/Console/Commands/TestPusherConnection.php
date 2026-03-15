<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\NotificationSent;
use App\Models\User;

class TestPusherConnection extends Command
{
    protected $signature = 'app:test-pusher {userId}';
    protected $description = 'Trigger a test Pusher notification for a specific user';

    public function handle()
    {
        $userId = $this->argument('userId');

        $notificationData = [
            'id' => uniqid(),
            'notification_message' => 'This is a test notification from BondKonnect CLI!',
            'type' => 'test',
            'created_at' => now()->toDateTimeString(),
        ];

        $this->info("Dispatching NotificationSent event for User ID: {$userId}...");
        
        try {
            broadcast(new NotificationSent($notificationData, $userId));
            $this->info("Success: Event broadcasted to 'notifications.{$userId}' channel.");
        } catch (\Exception $e) {
            $this->error("Failed to broadcast event: " . $e->getMessage());
        }

        return 0;
    }
}
