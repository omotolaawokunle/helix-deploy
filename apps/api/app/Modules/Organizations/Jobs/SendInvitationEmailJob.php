<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Jobs;

use App\Modules\Organizations\Mail\OrganizationInvitationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendInvitationEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly string $invitationUrl,
        public readonly string $organizationName,
        public readonly string $inviterName,
        public readonly ?string $teamName = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new OrganizationInvitationMail(
            organizationName: $this->organizationName,
            inviterName: $this->inviterName,
            invitationUrl: $this->spaInvitationUrl(),
            teamName: $this->teamName,
        ));
    }

    private function spaInvitationUrl(): string
    {
        $query = parse_url($this->invitationUrl, PHP_URL_QUERY);
        $spaUrl = rtrim((string) config('helixdeploy.spa_url'), '/');

        if ($query !== null && $query !== '') {
            return "{$spaUrl}/accept-invitation?{$query}";
        }

        return "{$spaUrl}/accept-invitation";
    }
}
