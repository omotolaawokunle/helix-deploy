<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Exceptions\CredentialAccessDeniedException;
use App\Modules\Deployments\Exceptions\ReleaseNotFoundException;
use App\Modules\Monitoring\Models\InfrastructureEvent;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Events\ServerFingerprintMismatch;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\Exceptions\SSHConnectionException;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Route::middleware(['web', 'auth:sanctum', 'verified'])
        ->prefix('api/v1/test/exceptions')
        ->group(function (): void {
            Route::get('/fingerprint/{server}', function (Server $server): void {
                throw new SSHFingerprintMismatchException(
                    server: $server,
                    expectedFingerprint: (string) $server->fingerprint,
                    receivedFingerprint: 'sha256:received-fingerprint',
                );
            });

            Route::get('/ssh-connection', function (): void {
                throw new SSHConnectionException('Connection timed out.');
            });

            Route::get('/credential-denied', function (): void {
                throw new CredentialAccessDeniedException('Credential access denied for this organization.');
            });

            Route::get('/release-not-found', function (): void {
                throw new ReleaseNotFoundException('Release directory no longer exists on server.');
            });

            Route::get('/audit-immutable', function (): void {
                $record = AuditLog::query()->first();
                abort_unless($record !== null, 404);
                $record->operation = 'tampered';
                $record->save();
            });
        });
});

it('returns 422 for ssh fingerprint mismatch without updating fingerprint', function (): void {
    Event::fake([ServerFingerprintMismatch::class]);

    [$server, $owner] = exceptionHandlingFixture(fingerprint: 'sha256:expected-stored');

    $this->actingAs($owner)
        ->getJson("/api/v1/test/exceptions/fingerprint/{$server->id}")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'SSH_FINGERPRINT_MISMATCH')
        ->assertJsonPath('message', 'Server fingerprint has changed. Connection blocked. Verify the server has not been compromised, then re-verify in Settings.');

    $server->refresh();

    expect($server->fingerprint)->toBe('sha256:expected-stored');

    Event::assertDispatched(ServerFingerprintMismatch::class);
    expect(InfrastructureEvent::query()
        ->where('event_type', 'server.fingerprint_mismatch')
        ->where('server_id', $server->id)
        ->exists())->toBeTrue();
});

it('returns 503 for ssh connection failures', function (): void {
    [, $owner] = exceptionHandlingFixture();

    $this->actingAs($owner)
        ->getJson('/api/v1/test/exceptions/ssh-connection')
        ->assertStatus(503)
        ->assertJsonPath('code', 'SSH_CONNECTION_FAILED');
});

it('returns 422 for dangerous commands before ssh manager is used', function (): void {
    $sshManager = \Mockery::mock(SSHManager::class);
    $sshManager->shouldNotReceive('connect');
    $this->instance(SSHManager::class, $sshManager);

    [$server, $owner] = exceptionHandlingFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/commands", [
            'command' => 'rm -rf /',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DANGEROUS_COMMAND_BLOCKED');
});

it('returns 403 for credential access denied', function (): void {
    [, $owner] = exceptionHandlingFixture();

    $this->actingAs($owner)
        ->getJson('/api/v1/test/exceptions/credential-denied')
        ->assertForbidden()
        ->assertJsonPath('code', 'CREDENTIAL_ACCESS_DENIED');
});

it('returns 404 for release not found', function (): void {
    [, $owner] = exceptionHandlingFixture();

    $this->actingAs($owner)
        ->getJson('/api/v1/test/exceptions/release-not-found')
        ->assertNotFound()
        ->assertJsonPath('code', 'RELEASE_NOT_FOUND');
});

it('returns 500 when audit log immutability is violated', function (): void {
    [$server, $owner, $organization] = exceptionHandlingFixture(returnOrganization: true);

    AuditLog::record('server.connection_test_requested', $server, metadata: [
        'organization_id' => (string) $organization->getKey(),
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/test/exceptions/audit-immutable')
        ->assertStatus(500)
        ->assertJsonPath('code', 'AUDIT_LOG_IMMUTABLE');
});

/**
 * @return array{0: Server, 1: User}|array{0: Server, 1: User, 2: Organization}
 */
function exceptionHandlingFixture(
    ?string $fingerprint = null,
    bool $returnOrganization = false,
): array {
    $organization = Organization::query()->create([
        'name' => 'Exception Handling Org',
        'slug' => 'exception-handling-'.Str::random(6),
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
        'hostname' => 'exceptions.test',
        'ip_address' => '10.0.0.99',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'fingerprint' => $fingerprint,
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    if ($returnOrganization) {
        return [$server, $owner, $organization];
    }

    return [$server, $owner];
}
