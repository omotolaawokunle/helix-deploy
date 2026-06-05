<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class TeamTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'teams.name',
            'teams.slug',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'teams.name',
            'teams.created_at',
            'teams.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'teams.name';
    }
}
