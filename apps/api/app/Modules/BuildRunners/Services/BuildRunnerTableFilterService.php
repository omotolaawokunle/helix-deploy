<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class BuildRunnerTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'build_runners.name',
            'build_runners.ip_address',
            'build_runners.ssh_user',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterableColumns(): array
    {
        return [
            'status' => 'build_runners.status',
            'project_id' => 'build_runners.project_id',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'build_runners.name',
            'build_runners.ip_address',
            'build_runners.status',
            'build_runners.created_at',
            'build_runners.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-build_runners.created_at';
    }
}
