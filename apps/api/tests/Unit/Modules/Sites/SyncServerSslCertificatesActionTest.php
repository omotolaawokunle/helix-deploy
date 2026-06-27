<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Actions\SyncServerSslCertificatesAction;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Services\SiteSslCertificateInspector;
use App\Packages\SSH\SSHManager;
use Tests\Support\SSH\PendingFakeSshConnection;

it('syncs ssl expiry for active sites on a server', function (): void {
    [$site, $fake] = siteSslProvisionerFixture();

    $site->forceFill([
        'enable_ssl' => true,
        'ssl_status' => SslStatus::ACTIVE->value,
    ])->save();

    queueSslExpiryInspectionResponses($fake, domain: $site->domain);

    test()->mock(SSHManager::class, function ($mock) use ($fake): void {
        $mock->shouldReceive('connect')->andReturn(new PendingFakeSshConnection($fake));
    });

    $action = new SyncServerSslCertificatesAction(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new SiteSslCertificateInspector(),
    );

    $result = $action->execute($site->server()->firstOrFail());

    expect($result['syncedCount'])->toBe(1)
        ->and($result['failedCount'])->toBe(0);

    $site->refresh();

    expect($site->ssl_expires_at)->not->toBeNull()
        ->and($site->ssl_checked_at)->not->toBeNull();

    expect(AuditLog::query()->where('operation', 'server.ssl_certificates.synced')->exists())->toBeTrue();
});

it('returns zero counts when server has no active ssl sites', function (): void {
    [$site] = siteSslProvisionerFixture();

    $site->forceFill([
        'enable_ssl' => false,
        'ssl_status' => SslStatus::NONE->value,
    ])->save();

    $action = new SyncServerSslCertificatesAction(
        app(SSHManager::class),
        app(\App\Modules\Credentials\CredentialVault::class),
        new SiteSslCertificateInspector(),
    );

    $result = $action->execute($site->server()->firstOrFail());

    expect($result)->toBe(['syncedCount' => 0, 'failedCount' => 0]);
});
