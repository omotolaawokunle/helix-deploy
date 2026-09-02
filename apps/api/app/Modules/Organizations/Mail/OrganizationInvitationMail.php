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
        public readonly ?string $teamName = null,
    ) {}

    public function invitationHeadline(): string
    {
        if ($this->teamName === null || $this->teamName === '') {
            return "You've been invited to {$this->organizationName}";
        }

        return "You've been invited to {$this->teamName} in {$this->organizationName}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->invitationHeadline(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.organization-invitation',
            with: [
                'invitationHeadline' => $this->invitationHeadline(),
            ],
        );
    }
}
