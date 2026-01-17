<?php

namespace App\Mail;

use Log;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class VerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $link;
    public $name;

    public $email;

    public function __construct($data)
    {
        $this->link=$data["verification_link"];
        $this->name=$data["name"];
        $this->email=$data["email"];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ' Verification Email',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Log::info($this->name);
        // Log::info('from register');
        return new Content(
            view: 'emails.verification_email',
            with: ['link' => $this->link,
            'name'=> $this->name,
            'email'=>$this->email,

            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
