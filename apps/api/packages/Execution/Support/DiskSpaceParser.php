<?php

declare(strict_types=1);

namespace App\Packages\Execution\Support;

final class DiskSpaceParser
{
    public static function parseAvailableBytes(string $dfOutput): ?int
    {
        $trimmed = trim($dfOutput);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^([\d.]+)\s*([KMGTPE]?i?B?)$/i', $trimmed, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2]);

        $multiplier = match (true) {
            str_starts_with($unit, 'T') => 1024 ** 4,
            str_starts_with($unit, 'G') => 1024 ** 3,
            str_starts_with($unit, 'M') => 1024 ** 2,
            str_starts_with($unit, 'K') => 1024,
            default => 1,
        };

        return (int) round($value * $multiplier);
    }
}
