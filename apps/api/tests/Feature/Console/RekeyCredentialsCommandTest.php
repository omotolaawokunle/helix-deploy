<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

it('rekeys organization master keys so secrets remain readable', function (): void {
    $oldAppKey = 'base64:'.base64_encode(random_bytes(32));
    $newAppKey = 'base64:'.base64_encode(random_bytes(32));

    Config::set('app.key', $oldAppKey);

    $organization = Organization::query()->create([
        'name' => 'Rekey Command Org',
        'slug' => 'rekey-command-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $vault = app(CredentialVault::class);
    $credential = $vault->storeSecret(
        organization: $organization,
        owner: $owner,
        name: 'cli-token',
        value: 'ROTATION_SECRET',
    );

    Config::set('app.key', $newAppKey);
    app()->forgetInstance(CredentialVaultInterface::class);
    app()->forgetInstance(CredentialVault::class);

    $oldKeyArgument = substr($oldAppKey, strlen('base64:'));

    $this->artisan('credentials:rekey', ['--old-key' => $oldKeyArgument])
        ->assertSuccessful();

    $vault = app(CredentialVault::class);
    $plaintext = $vault->getSecret((string) $credential->getKey(), $organization->refresh());

    expect($plaintext)->toBe('ROTATION_SECRET');
});
