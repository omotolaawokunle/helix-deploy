<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum LaravelWorkerType: string
{
    case HORIZON = 'horizon';
    case QUEUE = 'queue';
}
