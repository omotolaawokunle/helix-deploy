<?php

declare(strict_types=1);

namespace App\Modules\Teams\Services;

use App\Modules\Shared\Services\BaseTableFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TeamMemberTableFilterService extends BaseTableFilterService
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
            'role' => 'team_user.role',
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
            'team_user.created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'users.name';
    }
}
