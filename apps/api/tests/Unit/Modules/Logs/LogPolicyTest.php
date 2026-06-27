<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Servers\Enums\ServerLogType;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Policies\ServerPolicy;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Policies\SitePolicy;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('allows org members to view site logs', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Site Logs Policy Org',
        'slug' => 'site-logs-policy-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $site = Site::query()->make([
        'organization_id' => (string) $organization->getKey(),
    ]);
    $site->setRelation('organization', $organization);

    expect((new SitePolicy())->viewLogs($developer, $site))->toBeTrue();
});

it('allows org members to view server logs', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Server Logs Policy Org',
        'slug' => 'server-logs-policy-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($developer->getKey(), ['role' => TeamRole::DEVELOPER->value]);

    $server = Server::query()->make([
        'organization_id' => (string) $organization->getKey(),
    ]);
    $server->setRelation('organization', $organization);
    $server->setRelation('project', null);

    expect((new ServerPolicy())->viewLogs($developer, $server))->toBeTrue();
});

it('site log type enum values match api contract', function (): void {
    expect(SiteLogType::APPLICATION->value)->toBe('application');
});

it('server log type enum values match api contract', function (): void {
    expect(ServerLogType::NGINX_ACCESS->value)->toBe('nginx_access')
        ->and(ServerLogType::NGINX_ERROR->value)->toBe('nginx_error');
});
