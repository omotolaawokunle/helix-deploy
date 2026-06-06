<?php

declare(strict_types=1);

namespace App\Modules\Projects\Services;

use App\Modules\Shared\Services\BaseTableFilterService;

class EnvironmentTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'environments.name',
            'environments.label',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function filterableColumns(): array
    {
        return [
            'is_production' => 'environments.is_production',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'environments.name',
            'environments.created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'environments.name';
    }
}
