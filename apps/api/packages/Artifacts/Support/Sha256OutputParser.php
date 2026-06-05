<?php

declare(strict_types=1);

namespace App\Packages\Artifacts\Support;

final class Sha256OutputParser
{
    public static function parse(string $output): string
    {
        $parts = explode(' ', trim($output), 2);

        return $parts[0] ?? '';
    }
}
