<?php

declare(strict_types=1);

namespace App\Modules\Projects\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class ProjectTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'projects.name',
            'projects.description',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'projects.name',
            'projects.created_at',
            'projects.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-projects.created_at';
    }
}
