<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

use App\Modules\Sites\Enums\SiteLogReadMode;

final readonly class SiteLogReadTarget
{
    /**
     * @param list<string> $candidatePaths Directory paths for LATEST_GLOB, file paths for FILE
     */
    public function __construct(
        public SiteLogReadMode $mode,
        public string $path,
        public string $globPattern = '',
        public array $candidatePaths = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolvedPaths(): array
    {
        return $this->candidatePaths !== [] ? $this->candidatePaths : [$this->path];
    }

    /**
     * @param list<string> $candidateDirectories
     */
    public static function latestGlob(string $directory, string $pattern, array $candidateDirectories = []): self
    {
        $candidates = $candidateDirectories !== [] ? $candidateDirectories : [$directory];

        return new self(
            mode: SiteLogReadMode::LATEST_GLOB,
            path: $candidates[0],
            globPattern: $pattern,
            candidatePaths: $candidates,
        );
    }

    /**
     * @param list<string> $candidateFiles
     */
    public static function file(string $path, array $candidateFiles = []): self
    {
        $candidates = $candidateFiles !== [] ? $candidateFiles : [$path];

        return new self(
            mode: SiteLogReadMode::FILE,
            path: $candidates[0],
            candidatePaths: $candidates,
        );
    }
}
