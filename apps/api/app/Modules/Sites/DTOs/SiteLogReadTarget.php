<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

use App\Modules\Sites\Enums\SiteLogReadMode;

final readonly class SiteLogReadTarget
{
    public function __construct(
        public SiteLogReadMode $mode,
        public string $path,
        public string $globPattern = '',
    ) {
    }

    public static function file(string $path): self
    {
        return new self(SiteLogReadMode::FILE, $path);
    }

    public static function latestGlob(string $directory, string $pattern): self
    {
        return new self(SiteLogReadMode::LATEST_GLOB, $directory, $pattern);
    }
}
