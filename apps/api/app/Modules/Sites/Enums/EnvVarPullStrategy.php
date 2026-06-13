<?php

declare(strict_types=1);

namespace App\Modules\Sites\Enums;

enum EnvVarPullStrategy: string
{
    case ADD_NEW = 'add_new';
    case OVERWRITE_CHANGED = 'overwrite_changed';
    case MIRROR_SERVER = 'mirror_server';

    public function label(): string
    {
        return match ($this) {
            self::ADD_NEW => 'Import missing only',
            self::OVERWRITE_CHANGED => 'Import missing and overwrite changed',
            self::MIRROR_SERVER => 'Mirror server',
        };
    }
}
