<?php

declare(strict_types=1);

namespace App\Modules\Servers\Support;

final class SitePathMatcher
{
    /**
     * @param list<array{siteId: string, currentPath: string}> $sitePaths
     */
    public function match(string $directory, string $command, array $sitePaths): ?string
    {
        $directory = rtrim($directory, '/');

        foreach ($sitePaths as $site) {
            $currentPath = rtrim($site['currentPath'], '/');

            if ($currentPath === '') {
                continue;
            }

            if ($this->containsPath($directory, $currentPath) || $this->containsPath($command, $currentPath)) {
                return $site['siteId'];
            }
        }

        return null;
    }

    private function containsPath(string $haystack, string $path): bool
    {
        if ($haystack === '' || $path === '') {
            return false;
        }

        if ($haystack === $path) {
            return true;
        }

        return str_contains($haystack, $path.'/') || str_contains($haystack, $path.' ');
    }
}
