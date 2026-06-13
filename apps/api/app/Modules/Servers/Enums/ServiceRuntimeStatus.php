<?php

declare(strict_types=1);

namespace App\Modules\Servers\Enums;

enum ServiceRuntimeStatus: string
{
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';

    public static function fromSystemctlOutput(string $output): self
    {
        $normalized = strtolower(trim($output));

        return match ($normalized) {
            'active', 'running' => self::RUNNING,
            'inactive', 'dead' => self::STOPPED,
            'failed' => self::FAILED,
            default => self::UNKNOWN,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'Running',
            self::STOPPED => 'Stopped',
            self::FAILED => 'Failed',
            self::UNKNOWN => 'Unknown',
        };
    }
}
