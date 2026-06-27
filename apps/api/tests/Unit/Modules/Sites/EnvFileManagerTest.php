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
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
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

it('reads remote env file via ssh and returns empty when missing', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Env Read Org',
        'slug' => 'env-read-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create();
    $organization->users()->attach($owner->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-read.test',
        'ip_address' => '10.0.0.25',
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
        'domain' => 'read-env.example.test',
        'aliases' => [],
        'webroot' => '/var/www/read-env.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    $remotePath = app(EnvFileManager::class)->remotePath($site);
    $ssh = new FakeSSHConnection();

    $ssh->addSequence('test -f *',
        new SSHResult('test -f', 1, '', '', 0.0),
        new SSHResult('test -f', 1, '', '', 0.0),
        new SSHResult('test -f', 1, '', '', 0.0),
    );
    expect(app(EnvFileManager::class)->read($site, $ssh))->toBe('');

    $ssh = new FakeSSHConnection();
    $ssh->addResponse('test -f *', new SSHResult('test -f', 0, '', '', 0.0));
    $ssh->addResponse('cat *', new SSHResult('cat', 0, "APP_ENV=production\n", '', 0.0));

    expect(app(EnvFileManager::class)->read($site, $ssh))->toBe("APP_ENV=production\n");
    expect($remotePath)->toBe('/var/www/read-env.example.test/shared/.env');
});

it('reads env file from flat project root when directory name differs from domain', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Env Flat Org',
        'slug' => 'env-flat-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create();
    $organization->users()->attach($owner->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-flat.test',
        'ip_address' => '10.0.0.26',
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
        'domain' => 'api.example.test',
        'aliases' => [],
        'webroot' => '/var/www/api/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::DISCOVERED->value,
    ]);

    $ssh = new FakeSSHConnection();
    $ssh->addSequence('test -f *',
        new SSHResult('test -f', 1, '', '', 0.0),
        new SSHResult('test -f', 0, '', '', 0.0),
    );
    $ssh->addResponse('cat *', new SSHResult('cat', 0, "DB_HOST=127.0.0.1\n", '', 0.0));

    expect(app(EnvFileManager::class)->read($site, $ssh))->toBe("DB_HOST=127.0.0.1\n");
    expect(app(EnvFileManager::class)->remotePath($site))->toBe('/var/www/api/shared/.env');
});

it('sync writes to cached resolved path discovered during read', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Env Sync Cached Org',
        'slug' => 'env-sync-cached-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create();
    $organization->users()->attach($owner->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-sync-cached.test',
        'ip_address' => '10.0.0.27',
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
        'domain' => 'api.example.test',
        'aliases' => [],
        'webroot' => '/var/www/api/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::DISCOVERED->value,
    ]);

    app(CredentialVaultInterface::class)->storeSecret($organization, $site, 'APP_KEY', 'from-helix');

    $readSsh = new FakeSSHConnection();
    $readSsh->addSequence('test -f *',
        new SSHResult('test -f', 1, '', '', 0.0),
        new SSHResult('test -f', 0, '', '', 0.0),
    );
    $readSsh->addResponse('cat *', new SSHResult('cat', 0, "APP_KEY=remote\n", '', 0.0));

    $manager = app(EnvFileManager::class);
    $manager->read($site, $readSsh);

    $syncSsh = new FakeSSHConnection();
    $syncSsh->addSequence('chmod 640 *', new SSHResult('chmod', 0, '', '', 0.0));
    $syncSsh->addSequence('chown deploy:www-data *', new SSHResult('chown', 0, '', '', 0.0));

    $manager->sync($site, $organization, $syncSsh);

    expect($syncSsh->getUploads())->toHaveKey('/var/www/api/.env')
        ->and($syncSsh->getUploads()['/var/www/api/.env'])->toBe("APP_KEY=\"from-helix\"\n");
});

it('resolves referenced env vars from server secrets when generating env file', function (): void {
    $organization = Organization::query()->create([
        'name' => 'Env Reference Org',
        'slug' => 'env-reference-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create();
    $organization->users()->attach($owner->getKey(), ['role' => 'owner']);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'env-reference.test',
        'ip_address' => '10.0.0.28',
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
        'domain' => 'env-reference.example.test',
        'aliases' => [],
        'webroot' => '/var/www/env-reference.example.test/current/public',
        'runtime' => Runtime::PHP->value,
        'deploy_mode' => DeployMode::GIT->value,
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE->value,
    ]);

    $vault = app(CredentialVaultInterface::class);
    $serverSecret = $vault->storeServerSecret(
        $organization,
        $server,
        'env-reference.test-postgresql-deploy-password',
        'linked-postgres-password',
    );
    $vault->storeEnvVarReference(
        $organization,
        $site,
        'DB_PASSWORD',
        (string) $serverSecret->getKey(),
    );

    $content = app(EnvFileManager::class)->generate($site, $organization);

    expect($content)->toBe("DB_PASSWORD=\"linked-postgres-password\"\n");
});
