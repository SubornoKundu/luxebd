<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The security code that lets someone choose a new password. */
class PasswordCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiryMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' password reset code: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-code', text: 'emails.password-code-text', with: [
            'code' => $this->code,
            'expiryMinutes' => $this->expiryMinutes,
        ]);
    }
}
