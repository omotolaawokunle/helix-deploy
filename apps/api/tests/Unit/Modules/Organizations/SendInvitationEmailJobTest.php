<?php

declare(strict_types=1);

use App\Modules\Organizations\Jobs\SendInvitationEmailJob;
use Illuminate\Support\Facades\Mail;

it('sends the invitation email to the invitee with the spa accept url', function (): void {
    Mail::fake();

    config([
        'helixdeploy.spa_url' => 'https://app.helix.test',
    ]);

    (new SendInvitationEmailJob(
        email: 'invitee@example.test',
        invitationUrl: 'https://api.helix.test/api/v1/organizations/invitations/accept?token=abc&expires=1&signature=sig',
    ))->handle();

    Mail::assertOutgoingCount(1);
    Mail::assertSent(function (\Illuminate\Contracts\Mail\Mailable $mail): bool {
        return $mail->hasTo('invitee@example.test');
    });
});
