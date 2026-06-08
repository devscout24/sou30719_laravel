<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;
    public $company;
    public $loginUrl;

    public function __construct($user, $password, $company, $loginUrl)
    {
        $this->user     = $user;
        $this->password = $password;
        $this->company  = $company;
        $this->loginUrl = $loginUrl;
    }

    public function build()
    {
        return $this->subject('Welcome to ' . ($this->company->company_name ?? 'Our Platform'))
            ->view('emails.welcome_user');
    }
}
