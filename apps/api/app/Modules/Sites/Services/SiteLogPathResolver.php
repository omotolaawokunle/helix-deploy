<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\DTOs\SiteLogReadTarget;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Models\Site;

final class SiteLogPathResolver
{
    private const string LARAVEL_LOG_GLOB = 'laravel*.log';

    public function __construct(
        private readonly SiteDeployPathResolver $deployPathResolver,
    ) {
    }

    public function resolveTarget(Site $site): ?SiteLogReadTarget
    {
        return $this->resolveApplicationTarget($site);
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

        $logDirectories = $this->deployPathResolver->logDirectoryCandidates($site);
        $logFiles = $this->deployPathResolver->logFileCandidates($site, 'logs/error.log');

        return match ($runtime) {
            Runtime::PHP => SiteLogReadTarget::latestGlob(
                $logDirectories[0],
                self::LARAVEL_LOG_GLOB,
                $logDirectories,
            ),
            Runtime::NODEJS => SiteLogReadTarget::file(
                $logFiles[0],
                $logFiles,
            ),
            default => null,
        };
    }
}
