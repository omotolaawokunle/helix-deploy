<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Support;

use App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseRowFilterOperator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class DatabaseRowQueryFactory
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'filter' => ['sometimes', 'array', 'max:5'],
            'filter.*.column' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'filter.*.operator' => ['required', 'string', Rule::in(DatabaseRowFilterOperator::values())],
            'filter.*.value' => ['nullable', 'string', 'max:65535'],
        ];
    }

    public static function configureValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<int, array<string, mixed>>|null $filters */
            $filters = $validator->getData()['filter'] ?? null;

            if (! is_array($filters)) {
                return;
            }

            foreach ($filters as $index => $filter) {
                if (! is_array($filter)) {
                    continue;
                }

                $operator = DatabaseRowFilterOperator::tryFrom((string) ($filter['operator'] ?? ''));

                if ($operator === null) {
                    continue;
                }

                $value = $filter['value'] ?? null;
                $hasValue = is_string($value) && $value !== '';

                if ($operator->requiresValue() && ! $hasValue) {
                    $validator->errors()->add("filter.{$index}.value", 'A value is required for this filter operator.');
                }

                if (! $operator->requiresValue() && $hasValue) {
                    $validator->errors()->add("filter.{$index}.value", 'This filter operator does not accept a value.');
                }
            }
        });
    }

    public static function fromRequest(FormRequest $request): DatabaseRowQuery
    {
        $validated = $request->validated();

        /** @var list<array{column: string, operator: string, value?: string|null}> $rawFilters */
        $rawFilters = $validated['filter'] ?? [];

        $filters = [];

        foreach ($rawFilters as $rawFilter) {
            $operator = DatabaseRowFilterOperator::from((string) $rawFilter['operator']);
            $value = $rawFilter['value'] ?? null;

            $filters[] = new DatabaseRowFilter(
                column: (string) $rawFilter['column'],
                operator: $operator,
                value: is_string($value) && $value !== '' ? $value : null,
            );
        }

        return new DatabaseRowQuery(
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 50),
            filters: $filters,
        );
    }
}
