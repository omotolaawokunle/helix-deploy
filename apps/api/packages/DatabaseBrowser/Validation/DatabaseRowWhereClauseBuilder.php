<?php

declare(strict_types=1);

namespace App\Packages\DatabaseBrowser\Validation;

use App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use App\Packages\DatabaseBrowser\Enums\DatabaseRowFilterOperator;
use Illuminate\Validation\ValidationException;

final class DatabaseRowWhereClauseBuilder
{
    public function __construct(
        private readonly SqlIdentifierValidator $identifierValidator,
    ) {
    }

    public function build(DatabaseEngine $engine, DatabaseRowQuery $query): string
    {
        if ($query->filters === []) {
            return '';
        }

        $clauses = [];

        foreach ($query->filters as $filter) {
            $clauses[] = $this->buildClause($engine, $filter);
        }

        return ' WHERE '.implode(' AND ', $clauses);
    }

    private function buildClause(DatabaseEngine $engine, DatabaseRowFilter $filter): string
    {
        $this->identifierValidator->assertColumnName($filter->column);
        $column = $this->quoteColumn($engine, $filter->column);

        return match ($filter->operator) {
            DatabaseRowFilterOperator::IS_NULL => sprintf('%s IS NULL', $column),
            DatabaseRowFilterOperator::IS_NOT_NULL => sprintf('%s IS NOT NULL', $column),
            DatabaseRowFilterOperator::EQ => sprintf('%s = %s', $column, $this->literal($filter->value)),
            DatabaseRowFilterOperator::NEQ => sprintf('%s <> %s', $column, $this->literal($filter->value)),
            DatabaseRowFilterOperator::CONTAINS => sprintf(
                "%s LIKE %s ESCAPE '\\\\'",
                $column,
                $this->literal('%'.$this->escapeLike((string) $filter->value).'%'),
            ),
            DatabaseRowFilterOperator::STARTS_WITH => sprintf(
                "%s LIKE %s ESCAPE '\\\\'",
                $column,
                $this->literal($this->escapeLike((string) $filter->value).'%'),
            ),
            DatabaseRowFilterOperator::ENDS_WITH => sprintf(
                "%s LIKE %s ESCAPE '\\\\'",
                $column,
                $this->literal('%'.$this->escapeLike((string) $filter->value)),
            ),
            DatabaseRowFilterOperator::GT => sprintf('%s > %s', $column, $this->literal($filter->value)),
            DatabaseRowFilterOperator::GTE => sprintf('%s >= %s', $column, $this->literal($filter->value)),
            DatabaseRowFilterOperator::LT => sprintf('%s < %s', $column, $this->literal($filter->value)),
            DatabaseRowFilterOperator::LTE => sprintf('%s <= %s', $column, $this->literal($filter->value)),
        };
    }

    private function quoteColumn(DatabaseEngine $engine, string $column): string
    {
        if ($engine === DatabaseEngine::POSTGRESQL) {
            return '"'.str_replace('"', '""', $column).'"';
        }

        return '`'.str_replace('`', '``', $column).'`';
    }

    private function literal(?string $value): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                'filter' => ['Filter value is required for this operator.'],
            ]);
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
