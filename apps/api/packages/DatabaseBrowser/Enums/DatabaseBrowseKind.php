<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Enums;

enum DatabaseBrowseKind: string
{
    case DATABASES = 'databases';
    case TABLES = 'tables';
    case ROWS = 'rows';
}
