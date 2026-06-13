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
