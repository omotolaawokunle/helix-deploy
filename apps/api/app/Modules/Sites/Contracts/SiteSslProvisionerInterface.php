<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

use App\Modules\Sites\Models\Site;

interface SiteSslProvisionerInterface
{
    public function issue(Site $site): void;

    public function revoke(Site $site): void;

    public function renew(Site $site): void;
}
