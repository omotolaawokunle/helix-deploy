<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Jobs\FetchSiteNginxConfigJob;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteNginxProvisioner;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHManager;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

it('caches the nginx file read from the server', function (): void {
    $site = nginxConfigJobSite('nginx-job-read');
    $installed = "location /app {\n    proxy_pass http://127.0.0.1:8080;\n}\n";

    $fake = (new FakeSSHConnection)
        ->connect()
        ->addResponse(
            '*sites-available/nginx-job-read.example.test*',
            new SSHResult('cat', 0, $installed, '', 0.01),
        );

    $manager = Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $job = new FetchSiteNginxConfigJob((string) $site->getKey());
    $job->handle(new SiteNginxProvisioner($manager, app(CredentialVault::class)));

    $cached = Cache::get(FetchSiteNginxConfigJob::cacheKey((string) $site->getKey()));

    expect($cached['status'])->toBe('ready')
        ->and($cached['config'])->toBe($installed);

    $fake->assertCommandExecuted("sudo cat '/etc/nginx/sites-available/nginx-job-read.example.test'");
});

it('caches a failure when the server nginx file cannot be read', function (): void {
    $site = nginxConfigJobSite('nginx-job-missing');

    $fake = (new FakeSSHConnection)
        ->connect()
        ->addResponse(
            '*sites-available/nginx-job-missing.example.test*',
            new SSHResult('cat', 1, '', 'No such file or directory', 0.01),
        );

    $manager = Mockery::mock(SSHManager::class);
    $manager->shouldReceive('connect')->once()->andReturn($fake);

    $job = new FetchSiteNginxConfigJob((string) $site->getKey());
    $job->handle(new SiteNginxProvisioner($manager, app(CredentialVault::class)));

    $cached = Cache::get(FetchSiteNginxConfigJob::cacheKey((string) $site->getKey()));

    expect($cached['status'])->toBe('failed')
        ->and($cached['config'])->toBe('')
        ->and($cached['message'])->toBe('Unable to read the nginx configuration from the server.');
});

function nginxConfigJobSite(string $slug): Site
{
    $organization = Organization::query()->create([
        'name' => 'Nginx Job '.$slug,
        'slug' => $slug.'-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);

    $owner = User::factory()->create();

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => $slug.'.example.test',
        'ip_address' => '10.'.random_int(1, 200).'.'.random_int(0, 250).'.'.random_int(1, 250),
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    return Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => $slug.'.example.test',
        'webroot' => '/var/www/'.$slug.'.example.test',
        'runtime' => Runtime::PHP->value,
        'deploy_branch' => 'main',
        'status' => 'active',
    ]);
}
