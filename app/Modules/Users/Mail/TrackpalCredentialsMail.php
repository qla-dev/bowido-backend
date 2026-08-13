<?php

namespace App\Modules\Users\Mail;

use App\Modules\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrackpalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $credentialUser,
        public readonly string $temporaryPassword,
        public readonly bool $isPasswordReset = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isPasswordReset ? 'Trackpal wachtwoord opnieuw ingesteld' : 'Welkom bij Trackpal');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trackpal-credentials');
    }
}
