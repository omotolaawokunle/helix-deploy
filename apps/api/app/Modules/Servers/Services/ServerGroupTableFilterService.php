<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class ServerGroupTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'server_groups.name',
            'server_groups.description',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'server_groups.name',
            'server_groups.created_at',
            'server_groups.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'server_groups.name';
    }
}
