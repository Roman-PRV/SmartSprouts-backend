<?php

namespace App\Mail;

use App\Services\AccountDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-time code confirming account deletion for password-less accounts.
 */
class AccountDeletionCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code
    ) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.deletion_code.subject'),
        );
    }

    /**
     * The message content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account-deletion-code',
            with: [
                'minutes' => AccountDeletionService::CODE_TTL_MINUTES,
            ],
        );
    }
}
