<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class OrganizationTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'organizations.name',
            'organizations.slug',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'organizations.name',
            'organizations.slug',
            'organizations.created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-organizations.created_at';
    }
}
