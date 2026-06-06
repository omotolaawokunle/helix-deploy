<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Enums;

enum CloudflareConnectionStatus: string
{
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
}
