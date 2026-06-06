<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Enums;

enum ArtifactStorageType: string
{
    case LOCAL = 'local';
    case S3 = 's3';
    case R2 = 'r2';

    public function label(): string
    {
        return match ($this) {
            self::LOCAL => 'Local',
            self::S3 => 'S3',
            self::R2 => 'R2',
        };
    }
}
