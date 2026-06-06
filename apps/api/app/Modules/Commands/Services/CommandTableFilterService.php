<?php

declare(strict_types=1);

namespace App\Modules\Commands\Services;

use App\Modules\Shared\Services\BaseTableFilterService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CommandTableFilterService extends BaseTableFilterService
{
    protected function defaultSort(): string
    {
        return '-executed_at';
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['executed_at'];
    }

    /**
     * @return array<string, string|Closure(Builder, mixed, Request): void>
     */
    protected function filterableColumns(): array
    {
        return [
            'user_id' => 'user_id',
            'date_from' => function (Builder $query, mixed $value): void {
                $query->where('executed_at', '>=', $value);
            },
            'date_to' => function (Builder $query, mixed $value): void {
                $query->where('executed_at', '<=', $value);
            },
        ];
    }
}
