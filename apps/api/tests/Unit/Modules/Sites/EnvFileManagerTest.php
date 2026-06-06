<?php

declare(strict_types=1);

use App\Modules\Organizations\Models\Organization;
use App\Models\User;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\EnvFileManager;
use Illuminate\Support\Str;

it('generates valid KEY="VALUE" lines and escapes special characters', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Env File Org',
        'slug' => 'env-file-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create();
    $organization->users()->attach($owner->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-file.test',
        'ip_address' => '10.0.0.24',
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
        'domain' => 'env-file.example.test',
        'aliases' => [],
        'webroot' => '/var/www/env-file.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    $vault = app(CredentialVaultInterface::class);
    $vault->storeSecret($organization, $site, 'APP_MSG', 'line"break');

    $content = app(EnvFileManager::class)->generate($site, $organization);

    expect($content)->toBe("APP_MSG=\"line\\\"break\"\n");
});
