<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Actions\ClaimSiteAction;
use App\Modules\Sites\DTOs\ClaimSiteDTO;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('claims a discovered site and sets active status with repository details', function (): void {
    [$site, $owner] = claimSiteActionFixture();

    $result = app(ClaimSiteAction::class)->execute(
        site: $site,
        actor: $owner,
        dto: new ClaimSiteDTO(
            repositoryUrl: 'https://github.com/helix/example.git',
            repositoryProvider: 'github',
            deployBranch: 'main',
            autoDeployEnabled: false,
        ),
    );

    expect($result->site->status)->toBe(SiteStatus::ACTIVE)
        ->and($result->site->repository_url)->toBe('https://github.com/helix/example.git')
        ->and($result->site->repository_provider)->toBe('github')
        ->and($result->site->deploy_branch)->toBe('main')
        ->and($result->revealedWebhookSecret)->toBeNull();

    $audit = AuditLog::query()->where('operation', 'site.claimed')->first();
    expect($audit)->not->toBeNull()
        ->and($audit?->before_state)->toBe(['status' => SiteStatus::DISCOVERED->value])
        ->and($audit?->after_state['status'])->toBe(SiteStatus::ACTIVE->value)
        ->and($audit?->after_state['autoDeployEnabled'])->toBeFalse();
});

it('returns revealed webhook secret when auto deploy is enabled during claim', function (): void {
    [$site, $owner] = claimSiteActionFixture();

    $result = app(ClaimSiteAction::class)->execute(
        site: $site,
        actor: $owner,
        dto: new ClaimSiteDTO(
            repositoryUrl: 'https://github.com/helix/example.git',
            repositoryProvider: 'github',
            deployBranch: 'main',
            autoDeployEnabled: true,
        ),
    );

    expect($result->site->status)->toBe(SiteStatus::ACTIVE)
        ->and($result->site->auto_deploy_enabled)->toBeTrue()
        ->and($result->revealedWebhookSecret)->not->toBeNull()
        ->and($result->revealedWebhookSecret)->not->toBe('');
});

it('throws when claiming a site that is not discovered', function (): void {
    [$site, $owner] = claimSiteActionFixture(status: SiteStatus::ACTIVE);

    app(ClaimSiteAction::class)->execute(
        site: $site,
        actor: $owner,
        dto: new ClaimSiteDTO(
            repositoryUrl: 'https://github.com/helix/example.git',
            repositoryProvider: 'github',
            deployBranch: 'main',
            autoDeployEnabled: false,
        ),
    );
})->throws(InvalidArgumentException::class, 'Site is not in discovered status.');

/**
 * @return array{0: Site, 1: User}
 */
function claimSiteActionFixture(SiteStatus $status = SiteStatus::DISCOVERED): array
{
    $organization = Organization::query()->create([
        'name' => 'Claim Site Action Org',
        'slug' => 'claim-site-action-'.Str::random(6),
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
        'hostname' => 'claim-site-action.test',
        'ip_address' => '10.0.0.31',
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
        'domain' => 'discovered-claim.example.test',
        'aliases' => [],
        'webroot' => '/var/www/discovered-claim.example.test/public',
        'runtime' => Runtime::PHP,
        'deploy_mode' => DeployMode::GIT,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => $status,
    ]);

    return [$site, $owner];
}
