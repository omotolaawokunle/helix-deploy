<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrganizationInvitationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $inviterName,
        public readonly string $invitationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to {$this->organizationName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.organization-invitation',
        );
    }
}
