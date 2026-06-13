<?php

declare(strict_types=1);

use App\Modules\Sites\Services\EnvFileParser;

it('parses env file content with comments export and quoted values', function (): void {
    $content = <<<'ENV'
# comment
export APP_ENV=production
APP_KEY="base64:secret"
DB_HOST='localhost'
INVALID-KEY=bad
lowercase=skip
ENV;

    $result = app(EnvFileParser::class)->parse($content);

    expect($result['entries'])->toBe([
        'APP_ENV' => 'production',
        'APP_KEY' => 'base64:secret',
        'DB_HOST' => 'localhost',
    ]);
    expect($result['skipped'])->toHaveCount(2);
    expect($result['skipped'][0]['key'])->toBe('INVALID-KEY');
    expect($result['skipped'][1]['key'])->toBe('lowercase');
});

it('returns empty entries for blank content', function (): void {
    $result = app(EnvFileParser::class)->parse("\n\n# only comment\n");

    expect($result['entries'])->toBe([]);
    expect($result['skipped'])->toBe([]);
});
