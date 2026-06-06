<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class SiteTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'sites.domain',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterableColumns(): array
    {
        return [
            'runtime' => 'sites.runtime',
            'status' => 'sites.status',
            'server_id' => 'sites.server_id',
            'project_id' => 'sites.project_id',
            'environment_id' => 'sites.environment_id',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'sites.domain',
            'sites.runtime',
            'sites.status',
            'sites.created_at',
            'sites.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-sites.created_at';
    }
}
