<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Policies\SitePolicy;
use App\Modules\Teams\Enums\TeamRole;
use App\Modules\Sites\Enums\Runtime;
use Illuminate\Support\Str;

it('developer cannot delete sites', function (): void {
    [$organization, $owner] = createOrganizationOwnerForSitePolicy();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'policy-site.example.test',
        'ip_address' => '10.0.0.30',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'policy-site.example.test',
        'aliases' => [],
        'webroot' => '/var/www/policy-site.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => 'active',
    ]);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $policy = new SitePolicy();

    expect($policy->delete($developer, $site))->toBeFalse();
});

/**
 * @return array{Organization, User}
 */
function createOrganizationOwnerForSitePolicy(): array
{
    $organization = Organization::query()->create([
        'name' => 'Site Policy Org',
        'slug' => 'site-policy-org-'.Str::random(6),
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
