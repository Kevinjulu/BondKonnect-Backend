<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $mailableClass;
    protected $mailableData;
    protected $cc;
    protected $bcc;

    /**
     * Create a new job instance.
     */
    public function __construct(string $email, string $mailableClass, array $mailableData, array $cc = [], array $bcc = [])
    {
        $this->email = $email;
        $this->mailableClass = $mailableClass;
        $this->mailableData = $mailableData;
        $this->cc = $cc;
        $this->bcc = $bcc;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing background email for: {$this->email}");
        
        try {
            $mailable = new $this->mailableClass($this->mailableData);
            
            Mail::to($this->email)
                ->cc($this->cc)
                ->bcc($this->bcc)
                ->send($mailable);
                
            Log::info("Background email sent successfully to: {$this->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send background email to: {$this->email}", [
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to trigger queue retry
        }
    }
}
