<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\Models\Site;

final class SiteDeployPathResolver
{
    private const array WEBROOT_SUFFIXES = ['/current/public', '/current', '/public'];

    public function deployBase(Site $site): string
    {
        $webroot = rtrim((string) $site->webroot, '/');

        foreach (self::WEBROOT_SUFFIXES as $suffix) {
            if (str_ends_with($webroot, $suffix)) {
                return substr($webroot, 0, -strlen($suffix));
            }
        }

        return $webroot;
    }

    public function sharedDirectory(Site $site): string
    {
        return $this->deployBase($site).'/shared';
    }

    public function currentPath(Site $site): string
    {
        return $this->deployBase($site).'/current';
    }

    public function releasePath(Site $site, string $releaseId): string
    {
        return $this->deployBase($site).'/releases/'.$releaseId;
    }

    public function defaultEnvPath(Site $site): string
    {
        return $this->sharedDirectory($site).'/.env';
    }

    public function webrootPath(Site $site): string
    {
        return rtrim((string) $site->webroot, '/');
    }

    /**
     * @return list<string>
     */
    public function logDirectoryCandidates(Site $site): array
    {
        $base = $this->deployBase($site);
        $webroot = $this->webrootPath($site);
        $relativeLogDirs = ['logs', 'storage/logs'];
        $candidates = [];

        foreach ($relativeLogDirs as $relativeLogDir) {
            $candidates[] = $base.'/shared/'.$relativeLogDir;
            $candidates[] = $base.'/current/'.$relativeLogDir;
            $candidates[] = $webroot.'/'.$relativeLogDir;
        }

        if (str_ends_with($webroot, '/public')) {
            $candidates[] = dirname($webroot).'/storage/logs';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    public function logFileCandidates(Site $site, string $relativeFile): array
    {
        $relativeFile = ltrim($relativeFile, '/');
        $base = $this->deployBase($site);
        $webroot = $this->webrootPath($site);

        return array_values(array_unique([
            $base.'/shared/'.$relativeFile,
            $base.'/current/'.$relativeFile,
            $webroot.'/'.$relativeFile,
        ]));
    }

    /**
     * @return list<string>
     */
    public function envFileCandidates(Site $site): array
    {
        $base = $this->deployBase($site);

        return array_values(array_unique([
            $base.'/shared/.env',
            $base.'/.env',
            $base.'/current/.env',
        ]));
    }
}
