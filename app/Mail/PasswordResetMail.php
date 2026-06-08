<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetLink;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetLink, $user)
    {
        $this->resetLink = $resetLink;
        $this->user = $user;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset Your Password')
            ->view('emails.password.reset')
            ->with([
                'resetLink' => $this->resetLink,
                'user' => $this->user,
            ]);
    }
}
