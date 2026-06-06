<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services;

use App\Modules\Shared\Services\BaseTableFilterService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class DeploymentTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return ['branch', 'commit_hash', 'commit_message'];
    }

    /**
     * @return array<string, string|Closure(Builder, mixed, Request): void>
     */
    protected function filterableColumns(): array
    {
        return [
            'status' => 'status',
            'type' => 'type',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'started_at', 'finished_at', 'status'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
