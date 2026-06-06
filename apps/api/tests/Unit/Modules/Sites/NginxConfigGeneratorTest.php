<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\NginxConfigGenerator;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

it('generates php nginx config with correct fastcgi socket path', function (): void {
    $site = siteForNginxGenerator(Runtime::PHP, ['php_version' => '8.2']);

    $config = (new NginxConfigGenerator())->generate($site);

    expect($config)
        ->toContain('fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;')
        ->toContain('try_files $uri $uri/ /index.php?$query_string;');
});

it('generates node nginx config with proxy_pass', function (): void {
    $site = siteForNginxGenerator(Runtime::NODEJS, ['app_port' => 3000]);

    $config = (new NginxConfigGenerator())->generate($site);

    expect($config)
        ->toContain('proxy_pass http://127.0.0.1:3000;')
        ->toContain('proxy_set_header Host $host;');
});

it('generates ssl nginx config with redirect and certificate paths when ssl is active', function (): void {
    $site = siteForNginxGenerator(Runtime::PHP, [
        'php_version' => '8.3',
        'ssl_status' => 'active',
        'enable_ssl' => true,
    ]);

    $config = (new NginxConfigGenerator())->generate($site);

    expect($config)
        ->toContain('listen 443 ssl http2;')
        ->toContain('ssl_certificate /etc/letsencrypt/live/app.example.test/fullchain.pem;')
        ->toContain('return 301 https://$host$request_uri;');
});

/**
 * @param array<string, mixed> $overrides
 */
function siteForNginxGenerator(Runtime $runtime, array $overrides = []): Site
{
    $organization = Organization::query()->create([
        'name' => 'Nginx Generator Org',
        'slug' => 'nginx-generator-org-'.Str::random(6),
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
        'hostname' => 'nginx.example.test',
        'ip_address' => '10.0.0.20',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return Site::query()->create(array_merge([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'app.example.test',
        'aliases' => ['www.app.example.test'],
        'webroot' => '/var/www/app.example.test/current/public',
        'runtime' => $runtime->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => 'active',
    ], $overrides));
}
