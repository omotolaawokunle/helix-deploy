<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Jobs\SyncEnvVarsJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('lists env vars without encrypted values', function (): void {
    [$site, $owner, $credential] = envVarApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/env-vars")
        ->assertOk()
        ->assertJsonPath('data.0.key', 'APP_KEY')
        ->assertJsonPath('data.0.maskedValue', '••••••••')
        ->assertJsonMissingPath('data.0.encrypted_value')
        ->assertJsonMissingPath('data.0.encryptedValue')
        ->assertJsonMissingPath('data.0.value')
        ->assertJsonMissingPath('data.0.nonce');

    expect($credential->encrypted_value)->not->toBeEmpty();
});

it('reveals env var and writes audit log without the secret value', function (): void {
    [$site, $owner, $credential] = envVarApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/env-vars/{$credential->id}/reveal")
        ->assertOk()
        ->assertJsonPath('data.value', 'super-secret-value');

    $audit = AuditLog::query()
        ->where('operation', 'env_var.revealed')
        ->latest('created_at')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->after_state)->toHaveKey('key_name', 'APP_KEY');
    expect($audit->after_state)->not->toHaveKey('value');
    expect(json_encode($audit->after_state))->not->toContain('super-secret-value');
});

it('forbids developers from revealing env vars', function (): void {
    [$site, $owner, $credential, $organization] = envVarApiFixture();

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $this->actingAs($developer)
        ->getJson("/api/v1/sites/{$site->id}/env-vars/{$credential->id}/reveal")
        ->assertForbidden();
});

it('queues env var sync', function (): void {
    Queue::fake();

    [$site, $owner] = envVarApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/env-vars/sync")
        ->assertAccepted();

    Queue::assertPushed(SyncEnvVarsJob::class);
});

it('creates env var referencing a server secret', function (): void {
    [$site, $owner, , $organization] = envVarApiFixture();

    $server = $site->server;
    expect($server)->not->toBeNull();

    $vault = app(CredentialVaultInterface::class);
    $serverSecret = $vault->storeServerSecret(
        $organization,
        $server,
        'env-vars.test-postgresql-deploy-password',
        'postgres-deploy-secret',
    );

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/env-vars", [
            'key' => 'DB_PASSWORD',
            'referencedCredentialId' => (string) $serverSecret->getKey(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.key', 'DB_PASSWORD')
        ->assertJsonPath('data.isReference', true)
        ->assertJsonPath('data.referencedCredentialId', (string) $serverSecret->getKey())
        ->assertJsonPath('data.referencedCredentialLabel', 'PostgreSQL deploy password')
        ->assertJsonMissingPath('data.value');

    $envVar = Credential::query()->where('name', 'DB_PASSWORD')->first();
    expect($envVar)->not->toBeNull();
    expect($envVar->referenced_credential_id)->toBe((string) $serverSecret->getKey());
});

it('reveals referenced env var by resolving server secret', function (): void {
    [$site, $owner, , $organization] = envVarApiFixture();

    $server = $site->server;
    expect($server)->not->toBeNull();

    $vault = app(CredentialVaultInterface::class);
    $serverSecret = $vault->storeServerSecret(
        $organization,
        $server,
        'env-vars.test-redis-password',
        'redis-linked-secret',
    );

    $envVar = $vault->storeEnvVarReference(
        $organization,
        $site,
        'REDIS_PASSWORD',
        (string) $serverSecret->getKey(),
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/env-vars/{$envVar->id}/reveal")
        ->assertOk()
        ->assertJsonPath('data.value', 'redis-linked-secret');
});

it('lists linkable server credentials for a site', function (): void {
    [$site, $owner, , $organization] = envVarApiFixture();

    $server = $site->server;
    expect($server)->not->toBeNull();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeServerSecret(
        $organization,
        $server,
        'env-vars.test-mysql-deploy-password',
        'mysql-secret',
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/sites/{$site->id}/linkable-credentials")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.serviceKey', 'mysql')
        ->assertJsonMissing(['value', 'encrypted_value']);
});

it('rejects creating env var with both value and reference', function (): void {
    [$site, $owner, , $organization] = envVarApiFixture();

    $server = $site->server;
    $vault = app(CredentialVaultInterface::class);
    $serverSecret = $vault->storeServerSecret(
        $organization,
        $server,
        'env-vars.test-postgresql-deploy-password',
        'secret',
    );

    $this->actingAs($owner)
        ->postJson("/api/v1/sites/{$site->id}/env-vars", [
            'key' => 'DB_PASSWORD',
            'value' => 'literal',
            'referencedCredentialId' => (string) $serverSecret->getKey(),
        ])
        ->assertUnprocessable();
});

it('forbids updating referenced env vars', function (): void {
    [$site, $owner, , $organization] = envVarApiFixture();

    $server = $site->server;
    $vault = app(CredentialVaultInterface::class);
    $serverSecret = $vault->storeServerSecret(
        $organization,
        $server,
        'env-vars.test-postgresql-deploy-password',
        'secret',
    );

    $envVar = $vault->storeEnvVarReference(
        $organization,
        $site,
        'DB_PASSWORD',
        (string) $serverSecret->getKey(),
    );

    $this->actingAs($owner)
        ->patchJson("/api/v1/sites/{$site->id}/env-vars/{$envVar->id}", [
            'value' => 'new-value',
        ])
        ->assertForbidden();
});

/**
 * @return array{0: Site, 1: User, 2: Credential, 3: Organization}
 */
function envVarApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Env Vars API Org',
        'slug' => 'env-vars-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-vars.test',
        'ip_address' => '10.0.0.20',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'env-vars.example.test',
        'aliases' => [],
        'webroot' => '/var/www/env-vars.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    $vault = app(CredentialVaultInterface::class);
    $credential = $vault->storeSecret($organization, $site, 'APP_KEY', 'super-secret-value');
    expect($credential->type)->toBe(CredentialType::ENV_VAR);

    return [$site, $owner, $credential, $organization];
}
