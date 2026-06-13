<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\DTOs\SiteLogReadTarget;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteLogType;
use App\Modules\Sites\Models\Site;

final class SiteLogPathResolver
{
    private const string LARAVEL_LOG_GLOB = 'laravel*.log';

    public function resolveTarget(Site $site, SiteLogType $type): ?SiteLogReadTarget
    {
        return match ($type) {
            SiteLogType::NGINX_ACCESS => SiteLogReadTarget::file('/var/log/nginx/'.$site->domain.'-access.log'),
            SiteLogType::NGINX_ERROR => SiteLogReadTarget::file('/var/log/nginx/'.$site->domain.'-error.log'),
            SiteLogType::APPLICATION => $this->resolveApplicationTarget($site),
        };
    }

    public function supportsApplicationLogs(Site $site): bool
    {
        return $this->resolveApplicationTarget($site) !== null;
    }

    private function resolveApplicationTarget(Site $site): ?SiteLogReadTarget
    {
        $runtime = $site->runtime instanceof Runtime
            ? $site->runtime
            : Runtime::tryFrom((string) $site->runtime);

        if ($runtime === null) {
            return null;
        }

        $webroot = rtrim((string) $site->webroot, '/');

        return match ($runtime) {
            Runtime::PHP => SiteLogReadTarget::latestGlob(
                $webroot.'/storage/logs',
                self::LARAVEL_LOG_GLOB,
            ),
            Runtime::NODEJS => SiteLogReadTarget::file($webroot.'/logs/error.log'),
            default => null,
        };
    }
}
