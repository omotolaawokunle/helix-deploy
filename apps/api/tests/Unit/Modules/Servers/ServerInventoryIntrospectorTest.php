<?php

declare(strict_types=1);

use App\Modules\Servers\Services\ServerInventoryIntrospector;

it('parses discovered services and nginx sites from inventory output', function (): void {
    $introspector = new ServerInventoryIntrospector();

    $output = <<<'OUTPUT'
__HELIX_INVENTORY__
SVC:nginx
SVC:php
SVC:node
SVC:redis-cli
SITE:app.example.com|/var/www/app.example.com/public|php
SITE:api.example.com|/var/www/api|nodejs
SITE:default|/var/www/html|static
SITE:_|/var/www|static
__HELIX_END__
OUTPUT;

    $snapshot = $introspector->parseOutput($output);

    expect($snapshot->serviceKeys)->toBe(['nginx', 'php', 'nodejs', 'redis'])
        ->and($snapshot->sites)->toHaveCount(2)
        ->and($snapshot->sites[0]->domain)->toBe('app.example.com')
        ->and($snapshot->sites[0]->webroot)->toBe('/var/www/app.example.com/public')
        ->and($snapshot->sites[0]->runtime)->toBe('php')
        ->and($snapshot->sites[1]->domain)->toBe('api.example.com')
        ->and($snapshot->sites[1]->runtime)->toBe('nodejs');
});

it('returns empty snapshot when inventory markers are missing', function (): void {
    $introspector = new ServerInventoryIntrospector();

    $snapshot = $introspector->parseOutput("no markers here\n");

    expect($snapshot->serviceKeys)->toBe([])
        ->and($snapshot->sites)->toBe([]);
});
