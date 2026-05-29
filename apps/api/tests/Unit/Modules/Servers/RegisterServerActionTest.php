<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Actions\RegisterServerAction;
use App\Modules\Servers\DTOs\RegisterServerDTO;
use App\Modules\Servers\Enums\ManagementMode;
use App\Modules\Servers\Enums\ServerProvider;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Jobs\VerifyServerConnectionJob;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;

it('registers a server with generated key and connecting status', function (): void {
    Queue::fake();

    [$organization, $owner] = createOrganizationWithOwner();
    $action = app(RegisterServerAction::class);

    $result = $action->execute($organization, $owner, new RegisterServerDTO(
        name: 'Main Server',
        hostname: 'main.example.test',
        ipAddress: '192.168.10.5',
        sshPort: 22,
        sshUser: 'deploy',
        provider: ServerProvider::GENERIC,
        region: null,
        serverType: 'cx31',
        managementMode: ManagementMode::MANAGED,
        authMethod: 'generate',
        privateKey: null,
        projectId: null,
        environmentId: null,
        tags: ['edge'],
    ));

    $server = $result['server'];

    expect($result['publicKey'])->toBeString()->not->toBe('');
    expect($server->status)->toBe(ServerStatus::CONNECTING);
    expect($server->credential_id)->not->toBeNull();
    expect(Credential::query()->whereKey($server->credential_id)->exists())->toBeTrue();

    Queue::assertPushed(VerifyServerConnectionJob::class);
});

it('registers a server with imported key and connecting status', function (): void {
    Queue::fake();

    [$organization, $owner] = createOrganizationWithOwner();
    $action = app(RegisterServerAction::class);

    $result = $action->execute($organization, $owner, new RegisterServerDTO(
        name: 'Imported Server',
        hostname: 'imported.example.test',
        ipAddress: '10.10.10.10',
        sshPort: 2222,
        sshUser: 'root',
        provider: ServerProvider::AWS,
        region: 'eu-west-1',
        serverType: 't3.small',
        managementMode: ManagementMode::MANAGED,
        authMethod: 'import',
        privateKey: "-----BEGIN OPENSSH PRIVATE KEY-----\nabc\n-----END OPENSSH PRIVATE KEY-----",
        projectId: null,
        environmentId: null,
        tags: [],
    ));

    $server = $result['server'];

    expect($result['publicKey'])->toBeNull();
    expect($server->status)->toBe(ServerStatus::CONNECTING);
    expect($server->credential_id)->not->toBeNull();
    expect(Credential::query()->whereKey($server->credential_id)->exists())->toBeTrue();

    Queue::assertPushed(VerifyServerConnectionJob::class);
});

/**
 * @return array{Organization, User}
 */
function createOrganizationWithOwner(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $organization = Organization::query()->create([
        'name' => 'Servers Org',
        'slug' => 'servers-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner->forceFill(['current_organization_id' => (string) $organization->getKey()])->save();

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    return [$organization, $owner];
}
