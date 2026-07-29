<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Sites\DTOs\LaravelWorkerPreset;
use App\Modules\Sites\Enums\LaravelWorkerType;
use App\Modules\Sites\Models\Site;

final class LaravelWorkerPresetBuilder
{
    public function __construct(
        private readonly SiteDeployPathResolver $pathResolver,
    ) {
    }

    public function build(Site $site, LaravelWorkerType $workerType): LaravelWorkerPreset
    {
        $currentPath = $this->pathResolver->currentPath($site);
        $slug = $this->slugFromSite($site);

        $daemonName = match ($workerType) {
            LaravelWorkerType::HORIZON => $slug.'-horizon',
            LaravelWorkerType::QUEUE => $slug.'-queue',
        };

        $daemonCommand = match ($workerType) {
            LaravelWorkerType::HORIZON => 'php artisan horizon',
            LaravelWorkerType::QUEUE => 'php artisan queue:work --sleep=3 --tries=3 --max-time=3600',
        };

        $cronCommand = sprintf(
            'cd %s && php artisan schedule:run >> /dev/null 2>&1',
            $currentPath,
        );

        return new LaravelWorkerPreset(
            daemonName: $daemonName,
            daemonCommand: $daemonCommand,
            daemonDirectory: $currentPath,
            daemonUser: 'www-data',
            daemonProcesses: 1,
            cronExpression: '* * * * *',
            cronCommand: $cronCommand,
            cronUser: 'www-data',
        );
    }

    public function slugFromSite(Site $site): string
    {
        $source = (string) $site->domain;

        $slug = strtolower($source);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '' || ! preg_match('/^[a-z0-9]/', $slug)) {
            $slug = 'site-'.substr((string) $site->getKey(), 0, 8);
        }

        return $slug;
    }
}
