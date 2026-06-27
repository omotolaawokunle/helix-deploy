<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('lists server service credentials without secret values', function (): void {
    [$server, $owner, $organization] = serverServiceCredentialApiFixture();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeServerSecret(
        $organization,
        $server,
        'server-service.test-postgresql-deploy-password',
        'super-secret-password',
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/service-credentials")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.serviceKey', 'postgresql')
        ->assertJsonPath('data.0.label', 'PostgreSQL deploy password')
        ->assertJsonMissing(['value', 'encrypted_value']);
});

it('reveals server service credential and writes audit log', function (): void {
    [$server, $owner, $organization] = serverServiceCredentialApiFixture();

    $vault = app(CredentialVaultInterface::class);
    $credential = $vault->storeServerSecret(
        $organization,
        $server,
        'server-service.test-redis-password',
        'redis-secret-value',
    );

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/service-credentials/{$credential->id}/reveal")
        ->assertOk()
        ->assertJsonPath('data.value', 'redis-secret-value');

    expect(AuditLog::query()->where('operation', 'server_credential.revealed')->exists())->toBeTrue();
});

it('forbids developers from revealing server service credentials', function (): void {
    [$server, , $organization] = serverServiceCredentialApiFixture();

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $vault = app(CredentialVaultInterface::class);
    $credential = $vault->storeServerSecret(
        $organization,
        $server,
        'server-service.test-mysql-deploy-password',
        'mysql-secret',
    );

    $this->actingAs($developer)
        ->postJson("/api/v1/servers/{$server->id}/service-credentials/{$credential->id}/reveal")
        ->assertForbidden();
});

it('returns provisioning service version catalog', function (): void {
    [$server, $owner] = serverServiceCredentialApiFixture();

    $this->actingAs($owner)
        ->getJson('/api/v1/provisioning/service-versions')
        ->assertOk()
        ->assertJsonPath('data.postgresql.default', '16')
        ->assertJsonPath('data.mysql.default', '8.4')
        ->assertJsonFragment(['18']);
});

/**
 * @return array{0: Server, 1: User, 2: Organization}
 */
function serverServiceCredentialApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Server Credential Org',
        'slug' => 'server-credential-'.Str::random(6),
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
        'hostname' => 'server-service.test',
        'ip_address' => '10.0.0.58',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
        'credential_id' => null,
    ]);

    return [$server, $owner, $organization];
}
