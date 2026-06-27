<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Actions\AdoptServerSslCertificatesAction;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Services\SiteSslCertificateInspector;
use App\Packages\SSH\SSHManager;
use Tests\Support\SSH\PendingFakeSshConnection;

it('adopts existing lets encrypt certificate for a site without helix ssl flags', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $site->forceFill([
        'enable_ssl' => false,
        'ssl_status' => SslStatus::NONE->value,
    ])->save();

    queueSslExpiryInspectionResponses($fake, domain: $site->domain);

    test()->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->andReturn(new PendingFakeSshConnection($fake));
    });

    $action = new AdoptServerSslCertificatesAction(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new SiteSslCertificateInspector(),
    );

    $result = $action->execute($site->server()->firstOrFail());

    expect($result['adoptedCount'])->toBe(1)
        ->and($result['skippedCount'])->toBe(0);

    $site->refresh();

    expect($site->enable_ssl)->toBeTrue()
        ->and($site->ssl_status)->toBe(SslStatus::ACTIVE)
        ->and($site->ssl_expires_at)->not->toBeNull()
        ->and($site->ssl_provider?->value)->toBe('letsencrypt');

    expect(AuditLog::query()->where('operation', 'site.ssl_certificate.adopted')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'server.ssl_certificates.adopted')->exists())->toBeTrue();
});

it('skips sites that already have active ssl in helix', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $site->forceFill([
        'enable_ssl' => true,
        'ssl_status' => SslStatus::ACTIVE->value,
        'ssl_expires_at' => now()->addDays(60),
        'ssl_checked_at' => now(),
    ])->save();

    test()->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->andReturn(new PendingFakeSshConnection($fake));
    });

    $action = new AdoptServerSslCertificatesAction(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new SiteSslCertificateInspector(),
    );

    $result = $action->execute($site->server()->firstOrFail());

    expect($result)->toBe(['adoptedCount' => 0, 'skippedCount' => 1]);
});

it('skips observe mode servers', function (): void {
    [$site] = siteSslProvisionerFixture();

    $server = $site->server()->firstOrFail();
    $server->forceFill(['management_mode' => 'observe'])->save();

    $action = app(AdoptServerSslCertificatesAction::class);

    expect($action->execute($server))->toBe(['adoptedCount' => 0, 'skippedCount' => 0]);
});
