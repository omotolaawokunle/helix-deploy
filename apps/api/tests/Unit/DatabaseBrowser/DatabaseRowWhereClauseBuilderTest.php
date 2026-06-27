<?php

declare(strict_types=1);

use App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter;
use App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery;
use App\Packages\DatabaseBrowser\Enums\DatabaseEngine;
use App\Packages\DatabaseBrowser\Enums\DatabaseRowFilterOperator;
use App\Packages\DatabaseBrowser\Validation\DatabaseRowWhereClauseBuilder;

it('builds postgresql where clause with escaped like values', function (): void {
    $builder = app(DatabaseRowWhereClauseBuilder::class);

    $clause = $builder->build(
        DatabaseEngine::POSTGRESQL,
        new DatabaseRowQuery(filters: [
            new DatabaseRowFilter(
                column: 'email',
                operator: DatabaseRowFilterOperator::CONTAINS,
                value: '100%_done',
            ),
            new DatabaseRowFilter(
                column: 'deleted_at',
                operator: DatabaseRowFilterOperator::IS_NULL,
                value: null,
            ),
        ]),
    );

    expect($clause)->toContain('WHERE')
        ->and($clause)->toContain('"email" LIKE')
        ->and($clause)->toContain('"deleted_at" IS NULL')
        ->and($clause)->toContain('\\%')
        ->and($clause)->toContain('\\_');
});

it('rejects invalid column names in where builder', function (): void {
    $builder = app(DatabaseRowWhereClauseBuilder::class);

    $builder->build(
        DatabaseEngine::MYSQL,
        new DatabaseRowQuery(filters: [
            new DatabaseRowFilter(
                column: 'users;drop',
                operator: DatabaseRowFilterOperator::EQ,
                value: '1',
            ),
        ]),
    );
})->throws(\Illuminate\Validation\ValidationException::class);
