<?php

declare(strict_types=1);

use App\Modules\Integrations\Services\CloudflareHostnameResolver;

it('builds apex and subdomain hostnames from prefix', function (): void {
    $resolver = new CloudflareHostnameResolver();

    expect($resolver->buildFromPrefix('@', 'example.test'))->toBe('example.test');
    expect($resolver->buildFromPrefix('api', 'example.test'))->toBe('api.example.test');
    expect($resolver->isApex('example.test', 'example.test'))->toBeTrue();
    expect($resolver->recordName('api.example.test', 'example.test'))->toBe('api');
    expect($resolver->recordName('example.test', 'example.test'))->toBe('@');
});
