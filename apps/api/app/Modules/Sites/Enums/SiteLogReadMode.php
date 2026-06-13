<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum SiteLogReadMode: string
{
    case FILE = 'file';
    case LATEST_GLOB = 'latest_glob';
}
