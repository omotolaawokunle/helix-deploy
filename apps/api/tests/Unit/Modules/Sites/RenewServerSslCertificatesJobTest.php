<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Jobs\RenewServerSslCertificatesJob;
use App\Modules\Sites\Jobs\RenewSiteCertificateJob;
use Illuminate\Support\Facades\Queue;

it('dispatches staggered renewal jobs only for the target server', function (): void {
    Queue::fake();

    [$siteOne] = renewSiteCertificateFixture();
    [$siteTwo] = renewSiteCertificateFixture();

    $serverId = (string) $siteOne->server_id;

    $siteOne->forceFill([
        'enable_ssl' => true,
        'ssl_status' => SslStatus::ACTIVE->value,
    ])->save();

    $siteTwo->forceFill([
        'enable_ssl' => true,
        'ssl_status' => SslStatus::ACTIVE->value,
    ])->save();

    $job = new RenewServerSslCertificatesJob($serverId);
    $job->handle();

    Queue::assertPushed(RenewSiteCertificateJob::class, 1);
});
