<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Jobs\RenewSiteCertificateJob;
use App\Modules\Sites\Jobs\RenewSiteCertificatesJob;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{0: \App\Modules\Sites\Models\Site}
 */
function renewSiteCertificateFixture(): array
{
    $organization = \App\Modules\Organizations\Models\Organization::query()->create([
        'name' => 'Renewal Org',
        'slug' => 'renewal-org-'.\Illuminate\Support\Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => \App\Modules\Teams\Enums\TeamRole::OWNER->value]);

    $server = \App\Modules\Servers\Models\Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'renewal.example.test',
        'ip_address' => '10.0.0.60',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $site = \App\Modules\Sites\Models\Site::query()->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'renew-'.(\Illuminate\Support\Str::random(4)).'.example.test',
        'aliases' => [],
        'webroot' => '/var/www/renew.example.test/current/public',
        'runtime' => \App\Modules\Sites\Enums\Runtime::PHP->value,
        'deploy_mode' => 'git',
        'deploy_branch' => 'main',
        'run_migrations' => true,
        'docker_compose_path' => 'docker-compose.yml',
        'status' => 'active',
    ]);

    return [$site];
}

it('dispatches staggered per-site renewal jobs', function (): void {
    Queue::fake();

    [$siteOne] = renewSiteCertificateFixture();
    [$siteTwo] = renewSiteCertificateFixture();

    $siteOne->forceFill([
        'enable_ssl' => true,
        'ssl_status' => \App\Modules\Sites\Enums\SslStatus::ACTIVE->value,
    ])->save();

    $siteTwo->forceFill([
        'enable_ssl' => true,
        'ssl_status' => \App\Modules\Sites\Enums\SslStatus::ACTIVE->value,
    ])->save();

    $job = new RenewSiteCertificatesJob();
    $job->handle();

    Queue::assertPushed(RenewSiteCertificateJob::class, 2);
});

it('records audit log when single certificate renewal fails', function (): void {
    [$site] = renewSiteCertificateFixture();

    $provisioner = \Mockery::mock(\App\Modules\Sites\Contracts\SiteSslProvisionerInterface::class);
    $provisioner->shouldReceive('renew')->once()->andThrow(new RuntimeException('renewal failed'));

    $site->forceFill([
        'enable_ssl' => true,
        'ssl_status' => \App\Modules\Sites\Enums\SslStatus::ACTIVE->value,
    ])->save();

    $job = new RenewSiteCertificateJob((string) $site->getKey());

    expect(fn () => $job->handle($provisioner))
        ->toThrow(RuntimeException::class, 'renewal failed');

    expect(AuditLog::query()->where('operation', 'site.ssl_certificate.renewal_failed')->exists())->toBeTrue();
});
