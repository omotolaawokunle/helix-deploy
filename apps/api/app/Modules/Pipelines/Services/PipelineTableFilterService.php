<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Services;

use App\Modules\Shared\Services\BaseTableFilterService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PipelineTableFilterService extends BaseTableFilterService
{
    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [
            'pipelines.name',
            'pipelines.description',
        ];
    }

    /**
     * @return array<string, string|Closure(Builder, mixed, Request): void>
     */
    protected function filterableColumns(): array
    {
        return [
            'projectId' => 'pipelines.project_id',
        ];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [
            'pipelines.name',
            'pipelines.created_at',
            'pipelines.updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-pipelines.created_at';
    }
}
