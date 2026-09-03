<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Services\InvitationTokenService;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\URL;

it('accepts invitation when axios reorders signed query params', function (): void {
    config(['app.url' => 'https://deployer.test']);
    URL::forceRootUrl('https://deployer.test');
    URL::forceScheme('https');

    $organization = Organization::query()->create([
        'name' => 'Proxy Org',
        'slug' => 'proxy-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $invitee = User::factory()->create([
        'email' => 'proxy@example.test',
        'email_verified_at' => now(),
    ]);

    $token = app(InvitationTokenService::class)->encode(
        organizationId: (string) $organization->getKey(),
        email: 'proxy@example.test',
        role: TeamRole::ADMIN,
    );

    $signedUrl = URL::temporarySignedRoute(
        name: 'organizations.invitations.accept',
        expiration: now()->addDays(7),
        parameters: ['token' => $token],
    );

    parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $original);

    // Axios object key order from AcceptInvitationPage: token, expires, signature
    $axiosQuery = http_build_query([
        'token' => $original['token'],
        'expires' => $original['expires'],
        'signature' => $original['signature'],
    ]);

    $this->actingAs($invitee)
        ->postJson('/api/v1/organizations/invitations/accept?'.$axiosQuery)
        ->assertOk()
        ->assertJsonPath('data.organizationName', 'Proxy Org');
});
