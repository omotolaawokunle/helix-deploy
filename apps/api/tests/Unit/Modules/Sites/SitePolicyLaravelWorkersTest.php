<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Policies\SitePolicy;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('allows owner and admin to setup laravel workers', function (): void {
    [$site, $owner, $admin] = sitePolicyLaravelWorkersFixture();

    $policy = new SitePolicy();

    expect($policy->setupLaravelWorkers($owner, $site))->toBeTrue()
        ->and($policy->setupLaravelWorkers($admin, $site))->toBeTrue();
});

it('denies developer and viewer from setup laravel workers', function (): void {
    [$site, , , $developer, $viewer] = sitePolicyLaravelWorkersFixture(withDeveloper: true, withViewer: true);

    $policy = new SitePolicy();

    expect($policy->setupLaravelWorkers($developer, $site))->toBeFalse()
        ->and($policy->setupLaravelWorkers($viewer, $site))->toBeFalse();
});

/**
 * @return array{0: Site, 1: User, 2: User, 3?: User, 4?: User}
 */
function sitePolicyLaravelWorkersFixture(
    bool $withDeveloper = false,
    bool $withViewer = false,
): array {
    $organization = Organization::query()->create([
        'name' => 'Site Policy Laravel Workers Org',
        'slug' => 'site-policy-laravel-workers-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);
    $organization->users()->attach($admin->getKey(), ['role' => TeamRole::ADMIN->value]);

    $developer = null;
    $viewer = null;

    if ($withDeveloper) {
        $developer = User::factory()->create([
            'email_verified_at' => now(),
            'current_organization_id' => (string) $organization->getKey(),
        ]);
        $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);
    }

    if ($withViewer) {
        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'current_organization_id' => (string) $organization->getKey(),
        ]);
        $organization->users()->attach($viewer->getKey(), ['role' => TeamRole::VIEWER->value]);
    }

    $site = new Site([
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'policy.example.test',
    ]);
    $site->setRelation('organization', $organization);

    if ($withDeveloper && $withViewer && $developer !== null && $viewer !== null) {
        return [$site, $owner, $admin, $developer, $viewer];
    }

    return [$site, $owner, $admin];
}
