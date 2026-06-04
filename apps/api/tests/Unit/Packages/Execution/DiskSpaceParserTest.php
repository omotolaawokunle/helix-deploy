<?php

declare(strict_types=1);

use App\Packages\Execution\Support\DiskSpaceParser;

it('parses gigabyte disk space', function (): void {
    expect(DiskSpaceParser::parseAvailableBytes('10G'))->toBe(10 * 1024 ** 3);
});

it('parses megabyte disk space', function (): void {
    expect(DiskSpaceParser::parseAvailableBytes('512M'))->toBe(512 * 1024 ** 2);
});

it('returns null for unparseable output', function (): void {
    expect(DiskSpaceParser::parseAvailableBytes('unknown'))->toBeNull();
});
