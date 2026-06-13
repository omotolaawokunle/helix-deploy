<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

use App\Modules\Sites\Models\Site;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Carbon;

interface SiteSslCertificateInspectorInterface
{
    public function inspect(Site $site, SSHConnectionInterface $connection): ?Carbon;

    public function findCertificateExpiry(string $domain, SSHConnectionInterface $connection): ?Carbon;

    public function certificatePath(string $domain): string;

    public function parseOpenSslEndDate(string $output): ?Carbon;
}
