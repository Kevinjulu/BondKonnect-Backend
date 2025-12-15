<?php

namespace App\Listeners;

use App\Events\SendEmailEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Http\Controllers\V1\Defaults\CommunicationManagement;

class SendEmailListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SendEmailEvent $event): void
    {
        $emailData = $event->emailData;
        
        $communication = new CommunicationManagement();
        
        // For each recipient
        foreach ($emailData['recipients'] as $recipient) {
            $communication->composeMail(
                $recipient,
                '', // First name (can be fetched from DB if needed)
                false, // cc
                false, // bcc
                false, // attachments
                true,  // is_system
                false, // is_scheduled
                false, // is_template
                false, // save_template
                $emailData['subject'],
                $emailData['body']
            );
        }
    }
} 