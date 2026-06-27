<?php

declare(strict_types=1);

use App\Packages\DatabaseBrowser\Validation\SqlIdentifierValidator;

it('rejects invalid sql identifiers', function (): void {
    $validator = new SqlIdentifierValidator();

    expect(fn () => $validator->assertTableName('users;drop'))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $validator->assertDatabaseName('my-db'))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('accepts valid sql identifiers', function (): void {
    $validator = new SqlIdentifierValidator();

    $validator->assertDatabaseName('app_production');
    $validator->assertTableName('users');

    expect(true)->toBeTrue();
});
