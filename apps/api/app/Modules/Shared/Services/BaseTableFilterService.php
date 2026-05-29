<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseTableFilterService
{
    protected const DEFAULT_PER_PAGE = 15;

    protected const MAX_PER_PAGE = 100;

    public function paginate(Builder|Relation $query, Request $request): LengthAwarePaginator
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        $this->applySearch($builder, $request);
        $this->applyFilters($builder, $request);
        $this->applySort($builder, $request);

        $paginator = $builder->paginate(
            perPage: $this->resolvePerPage($request),
            page: (int) $request->integer('page', 1),
        );

        $paginator->appends($request->query());

        return $paginator;
    }

    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return [];
    }

    /**
     * @return array<string, string|Closure(Builder, mixed, Request): void>
     */
    protected function filterableColumns(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    private function applySearch(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $columns = $this->searchableColumns();

        if ($search === '' || $columns === []) {
            return;
        }

        $query->where(function (Builder $builder) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $builder->where($column, 'like', "%{$search}%");

                    continue;
                }

                $builder->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $filters = $request->query('filter', []);

        if (! is_array($filters) || $filters === []) {
            return;
        }

        foreach ($this->filterableColumns() as $key => $filterResolver) {
            $value = $filters[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if ($filterResolver instanceof Closure) {
                $filterResolver($query, $value, $request);

                continue;
            }

            $query->where($filterResolver, $value);
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sortable = $this->sortableColumns();

        if ($sortable === []) {
            return;
        }

        $requestedSort = trim((string) $request->query('sort', $this->defaultSort()));
        $tokens = array_values(array_filter(array_map('trim', explode(',', $requestedSort))));

        if ($tokens === []) {
            $tokens = [$this->defaultSort()];
        }

        $applied = false;

        foreach ($tokens as $token) {
            $direction = str_starts_with($token, '-') ? 'desc' : 'asc';
            $column = ltrim($token, '-');

            if (! in_array($column, $sortable, true)) {
                continue;
            }

            $query->orderBy($column, $direction);
            $applied = true;
        }

        if (! $applied) {
            $default = $this->defaultSort();
            $direction = str_starts_with($default, '-') ? 'desc' : 'asc';
            $column = ltrim($default, '-');

            if (in_array($column, $sortable, true)) {
                $query->orderBy($column, $direction);
            }
        }
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        if ($perPage <= 0) {
            return self::DEFAULT_PER_PAGE;
        }

        if ($perPage > self::MAX_PER_PAGE) {
            return self::MAX_PER_PAGE;
        }

        return $perPage;
    }
}
