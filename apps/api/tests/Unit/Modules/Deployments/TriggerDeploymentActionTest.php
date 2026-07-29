<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Deployments\Actions\TriggerDeploymentAction;
use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('persists webhook trigger metadata with null actor', function (): void {
    Queue::fake();

    $organization = Organization::query()->create([
        'name' => 'Trigger Action Org',
        'slug' => 'trigger-action-'.Str::random(6),
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
        'hostname' => 'trigger-action.test',
        'ip_address' => '10.0.0.40',
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
        'domain' => 'trigger-action.example.test',
        'aliases' => [],
        'webroot' => '/var/www/trigger-action.example.test/current/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
    ]);

    $deployment = app(TriggerDeploymentAction::class)->execute(
        site: $site,
        actor: null,
        dto: new TriggerDeploymentDTO(
            branch: 'main',
            triggerType: TriggerType::WEBHOOK,
            commitHash: 'abc123',
            commitMessage: 'Webhook deploy',
        ),
    );

    expect($deployment->trigger_type)->toBe(TriggerType::WEBHOOK);
    expect($deployment->triggered_by)->toBeNull();
    expect($deployment->commit_hash)->toBe('abc123');
    expect($deployment->commit_message)->toBe('Webhook deploy');

    $persisted = Deployment::query()
        ->withoutGlobalScope('owned_by_organization')
        ->findOrFail($deployment->getKey());

    expect($persisted->trigger_type)->toBe(TriggerType::WEBHOOK);
});
