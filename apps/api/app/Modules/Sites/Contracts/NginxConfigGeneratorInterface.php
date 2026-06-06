<?php

declare(strict_types=1);

namespace App\Modules\Sites\Contracts;

use App\Modules\Sites\Models\Site;

interface NginxConfigGeneratorInterface
{
    public function generate(Site $site): string;
}
