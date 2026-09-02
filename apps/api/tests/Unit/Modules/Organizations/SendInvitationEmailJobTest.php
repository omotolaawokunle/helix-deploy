<?php

declare(strict_types=1);

use App\Modules\Organizations\Jobs\SendInvitationEmailJob;
use App\Modules\Organizations\Mail\OrganizationInvitationMail;
use Illuminate\Support\Facades\Mail;

it('sends the invitation email to the invitee with the spa accept url', function (): void {
    Mail::fake();

    config([
        'helixdeploy.spa_url' => 'https://app.helix.test',
    ]);

    (new SendInvitationEmailJob(
        email: 'invitee@example.test',
        invitationUrl: 'https://api.helix.test/api/v1/organizations/invitations/accept?token=abc&expires=1&signature=sig',
        organizationName: 'Acme',
        inviterName: 'Jane Doe',
    ))->handle();

    Mail::assertOutgoingCount(1);
    Mail::assertSent(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail): bool {
        return $mail->hasTo('invitee@example.test')
            && $mail->organizationName === 'Acme'
            && $mail->inviterName === 'Jane Doe'
            && $mail->teamName === null
            && $mail->invitationHeadline() === "You've been invited to Acme"
            && $mail->invitationUrl === 'https://app.helix.test/accept-invitation?token=abc&expires=1&signature=sig';
    });
});

it('includes the team name in the invitation email when a team is selected', function (): void {
    Mail::fake();

    config([
        'helixdeploy.spa_url' => 'https://app.helix.test',
    ]);

    (new SendInvitationEmailJob(
        email: 'invitee@example.test',
        invitationUrl: 'https://api.helix.test/api/v1/organizations/invitations/accept?token=abc&expires=1&signature=sig',
        organizationName: 'Acme',
        inviterName: 'Jane Doe',
        teamName: 'Platform',
    ))->handle();

    Mail::assertSent(OrganizationInvitationMail::class, function (OrganizationInvitationMail $mail): bool {
        return $mail->hasTo('invitee@example.test')
            && $mail->teamName === 'Platform'
            && $mail->invitationHeadline() === "You've been invited to Platform in Acme";
    });
});
