<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Auth;

it('records the authenticated actor when a private key is accessed', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Audit Org',
        'slug' => 'audit-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    $vault = app(CredentialVaultInterface::class);
    $credential = $vault->storePrivateKey(
        organization: $organization,
        owner: $organization,
        name: 'server-key',
        key: 'PRIVATE_KEY_MATERIAL',
    );

    Auth::login($user);
    $vault->getPrivateKey((string) $credential->getKey(), $organization);

    $log = AuditLog::query()
        ->where('operation', 'credential.accessed')
        ->where('resource_id', (string) $credential->getKey())
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log?->actor_id)->toBe((string) $user->getKey());
});

it('leaves actor null when a private key is accessed without an authenticated user', function (): void {
    $organization = Organization::query()->create([
        'name' => 'System Audit Org',
        'slug' => 'system-audit-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    Auth::login($owner);
    $vault = app(CredentialVaultInterface::class);
    $credential = $vault->storePrivateKey(
        organization: $organization,
        owner: $organization,
        name: 'system-key',
        key: 'PRIVATE_KEY_MATERIAL',
    );

    Auth::logout();
    $vault->getPrivateKey((string) $credential->getKey(), $organization);

    $log = AuditLog::query()
        ->where('operation', 'credential.accessed')
        ->where('resource_id', (string) $credential->getKey())
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log?->actor_id)->toBeNull();
});
