<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Services;

use App\Modules\Shared\Services\BaseTableFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrganizationMemberTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'users.name',
            'users.email',
        ];
    }

    /**
     * @return array<string, string|\Closure(Builder, mixed, Request): void>
     */
    protected function filterableColumns(): array
    {
        return [
            'role' => 'organization_users.role',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'users.name',
            'users.email',
            'organization_users.created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'users.name';
    }
}
