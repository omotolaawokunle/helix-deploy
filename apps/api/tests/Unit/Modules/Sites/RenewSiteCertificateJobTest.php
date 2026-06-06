<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Sites\Jobs\RenewSiteCertificateJob;
use App\Modules\Sites\Jobs\RenewSiteCertificatesJob;
use Illuminate\Support\Facades\Queue;

it('dispatches staggered per-site renewal jobs', function (): void {
    Queue::fake();

    $job = new RenewSiteCertificatesJob();
    $job->handle();

    Queue::assertPushed(RenewSiteCertificateJob::class, 0);
});

it('records audit log when single certificate renewal fails', function (): void {
    [$site] = siteSslProvisionerFixture();

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
