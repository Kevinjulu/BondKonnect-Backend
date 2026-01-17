<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $name;
    public $email;

    public function __construct($data)
    {
        $this->otp = $data["otp"];
        $this->name = $data["name"];
        $this->email = $data["email"];
    }

    // public function build()
    // {
    //     return $this->view('emails.otp')
    //                 ->with([
    //                     'otp' => $this->otp,
    //                 ]);
    // }
        /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Otp Verification',
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
            view: 'emails.otp',
            with: ['otp' => $this->otp,
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
