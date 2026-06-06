<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Commands\Jobs\RunCommandJob;
use App\Modules\Commands\Models\Command;
use App\Modules\Commands\Services\CommandService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('returns confirmation required for warn commands without confirmed flag', function (): void {
    Queue::fake();

    [$server, $owner] = commandApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/commands", [
            'command' => 'sudo ls -la',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'confirmation_required')
        ->assertJsonPath('warningType', 'sudo');

    Queue::assertNothingPushed();
});

it('queues execution when warn command is confirmed', function (): void {
    Queue::fake();

    [$server, $owner] = commandApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/commands", [
            'command' => 'sudo ls -la',
            'confirmed' => true,
        ])
        ->assertAccepted()
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(RunCommandJob::class);
});

it('returns 422 for blocked commands', function (): void {
    Queue::fake();

    [$server, $owner] = commandApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/commands", [
            'command' => 'rm -rf /',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DANGEROUS_COMMAND_BLOCKED');

    Queue::assertNothingPushed();
});

it('requires confirmation for warn commands on production servers', function (): void {
    Queue::fake();

    [$server, $owner] = commandApiFixture(production: true);

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/commands", [
            'command' => 'sudo service nginx status',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'confirmation_required');

    Queue::assertNothingPushed();
});

it('returns queued command record with id for streaming', function (): void {
    Queue::fake();

    [$server, $owner] = commandApiFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/servers/{$server->id}/commands", [
            'command' => 'uptime',
            'timeout' => 90,
        ])
        ->assertAccepted()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('data.command', 'uptime')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.timeoutSeconds', 90)
        ->assertJsonStructure(['data' => ['id']]);

    Queue::assertPushed(RunCommandJob::class);
});

it('cancels a pending command', function (): void {
    [$server, $owner, $organization] = commandApiFixture(returnOrg: true);

    $record = app(CommandService::class)->queue($server, 'uptime', $owner, $organization);

    $this->actingAs($owner)
        ->postJson("/api/v1/commands/{$record->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($record->refresh()->status->value)->toBe('cancelled');
});

it('executes confirmed warn commands and omits output from audit log', function (): void {
    [$server, $owner, $organization] = commandApiFixture(returnOrg: true);

    $fake = (new FakeSSHConnection())->connect();
    $fake->addResponse('sudo hostname*', new SSHResult(
        command: 'sudo hostname',
        exitCode: 0,
        stdout: "prod-host\n",
        stderr: '',
        duration: 0.1,
    ));

    $this->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->once()->andReturn($fake);
    });

    $service = app(CommandService::class);
    $queued = $service->queue($server, 'sudo hostname', $owner, $organization);
    $service->execute($queued);

    $command = Command::query()->latest('executed_at')->first();
    expect($command?->output)->toContain('prod-host');

    $audit = AuditLog::query()->where('operation', 'command.executed')->latest('created_at')->first();
    expect($audit?->after_state)->toHaveKeys(['command_text', 'exit_code', 'server_id'])
        ->and(json_encode($audit?->after_state))->not->toContain('prod-host');
});

/**
 * @return array{0: Server, 1: User}|array{0: Server, 1: User, 2: Organization}
 */
function commandApiFixture(bool $production = false, bool $returnOrg = false): array
{
    $organization = Organization::query()->create([
        'name' => 'Command API Org',
        'slug' => 'command-api-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $environmentId = null;

    if ($production) {
        $project = Project::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'name' => 'Command API Project',
        ]);
        $environment = Environment::query()->create([
            'project_id' => (string) $project->getKey(),
            'organization_id' => (string) $organization->getKey(),
            'name' => 'production',
            'label' => 'Production',
            'is_production' => true,
        ]);
        $environmentId = (string) $environment->getKey();
    }

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'environment_id' => $environmentId,
        'hostname' => 'command-api.test',
        'ip_address' => '10.0.0.56',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    if ($returnOrg) {
        return [$server, $owner, $organization];
    }

    return [$server, $owner];
}
