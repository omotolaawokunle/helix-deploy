<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Policies\ServerPolicy;
use App\Modules\Servers\Models\Server;
use App\Modules\Teams\Enums\TeamRole;

it('developer cannot provision or delete a server', function (): void {
    [$organization, $owner] = createOrganizationOwnerForServerPolicy();

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Policy Project',
        'description' => null,
    ]);

    $environment = Environment::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'name' => 'staging',
        'label' => 'Staging',
        'is_production' => false,
    ]);

    $server = Server::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'environment_id' => (string) $environment->getKey(),
        'hostname' => 'policy.example.test',
        'ip_address' => '10.0.0.12',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $policy = new ServerPolicy();

    expect($policy->provision($developer, $server))->toBeFalse()
        ->and($policy->delete($developer, $server))->toBeFalse();
});

/**
 * @return array{Organization, User}
 */
function createOrganizationOwnerForServerPolicy(): array
{
    $organization = Organization::query()->create([
        'name' => 'Policy Org',
        'slug' => 'policy-org',
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    return [$organization, $owner];
}
