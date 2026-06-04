<?php

declare(strict_types=1);

use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Policies\DeploymentPolicy;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Teams\Enums\TeamRole;

it('allows developers to deploy to non-production environments', function (): void {
    [$site, $developer] = deploymentPolicyFixture(TeamRole::DEVELOPER, isProduction: false);

    expect((new DeploymentPolicy())->execute($developer, $site))->toBeTrue();
});

it('denies developers from deploying to production environments', function (): void {
    [$site, $developer] = deploymentPolicyFixture(TeamRole::DEVELOPER, isProduction: true);

    expect((new DeploymentPolicy())->execute($developer, $site))->toBeFalse();
});

it('denies developers from rolling back production deployments', function (): void {
    [$site, $developer] = deploymentPolicyFixture(TeamRole::DEVELOPER, isProduction: true);

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => 'deploy',
        'status' => 'success',
        'trigger_type' => 'manual',
        'branch' => 'main',
        'finished_at' => now(),
    ]);

    expect((new DeploymentPolicy())->rollback($developer, $deployment))->toBeFalse();
});

it('allows org members to view deployment logs', function (): void {
    [$site, $developer] = deploymentPolicyFixture(TeamRole::DEVELOPER, isProduction: true);

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => 'deploy',
        'status' => 'pending',
        'trigger_type' => 'manual',
        'branch' => 'main',
    ]);

    expect((new DeploymentPolicy())->viewLogs($developer, $deployment))->toBeTrue();
});

it('allows only admins and owners to cancel deployments', function (): void {
    [$site, $admin] = deploymentPolicyFixture(TeamRole::ADMIN, isProduction: false);

    $developer = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $site->organization_id,
    ]);
    \App\Modules\Organizations\Models\Organization::query()
        ->whereKey($site->organization_id)
        ->first()
        ?->users()
        ->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => 'deploy',
        'status' => 'running',
        'trigger_type' => 'manual',
        'branch' => 'main',
    ]);

    $policy = new DeploymentPolicy();

    expect($policy->cancel($admin, $deployment))->toBeTrue()
        ->and($policy->cancel($developer, $deployment))->toBeFalse();
});

/**
 * @return array{0: \App\Modules\Sites\Models\Site, 1: \App\Models\User}
 */
function deploymentPolicyFixture(TeamRole $role, bool $isProduction = false): array
{
    $organization = \App\Modules\Organizations\Models\Organization::query()->create([
        'name' => 'Policy Org',
        'slug' => 'policy-org-'.\Illuminate\Support\Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    $server = \App\Modules\Servers\Models\Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'policy-server.test',
        'ip_address' => '127.0.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $user->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Policy Project',
    ]);

    $environment = Environment::query()->create([
        'project_id' => (string) $project->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'name' => $isProduction ? 'production' : 'staging',
        'label' => $isProduction ? 'Production' : 'Staging',
        'is_production' => $isProduction,
    ]);

    $site = \App\Modules\Sites\Models\Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'environment_id' => (string) $environment->getKey(),
        'domain' => 'policy.example.test',
        'aliases' => [],
        'webroot' => '/var/www/policy.example.test/current',
        'runtime' => 'php',
        'status' => 'active',
    ]);

    return [$site, $user];
}
