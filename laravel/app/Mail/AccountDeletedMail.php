<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notice that the account was permanently deleted.
 *
 * Built from plain strings — by the time this is queued the User row is gone,
 * so there is no model left to serialize.
 */
class AccountDeletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name
    ) {}

    /**
     * The message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.account_deleted.subject'),
        );
    }

    /**
     * The message content.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account-deleted',
        );
    }
}
