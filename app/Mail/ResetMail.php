<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $newEmail;
    public $otp;
    public $link;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $newEmail, $otp, $link)
    {
        $this->user     = $user;
        $this->newEmail = $newEmail;
        $this->otp      = $otp;
        $this->link     = $link;

    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Email Change Verification'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.email-reset',
            with: [
                'name'      => $this->user->name,
                'newEmail'  => $this->newEmail,
                'otp'       => $this->otp,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
