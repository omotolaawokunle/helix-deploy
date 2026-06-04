<?php

declare(strict_types=1);

use App\Modules\Commands\Enums\CommandWarningType;
use App\Modules\Commands\Exceptions\DangerousCommandException;
use App\Modules\Commands\Services\DangerousCommandGuard;

it('blocks destructive root rm commands', function (): void {
    $guard = new DangerousCommandGuard();

    expect(fn () => $guard->check('rm -rf /'))->toThrow(DangerousCommandException::class);
    expect(fn () => $guard->check('RM -RF /*'))->toThrow(DangerousCommandException::class);
});

it('warns on sudo commands without blocking', function (): void {
    $guard = new DangerousCommandGuard();

    $guard->check('sudo systemctl status nginx');

    expect($guard->warn('sudo systemctl status nginx'))->toBeTrue()
        ->and($guard->warningType('sudo systemctl status nginx'))->toBe(CommandWarningType::SUDO);
});

it('warns on non root rm -rf without blocking', function (): void {
    $guard = new DangerousCommandGuard();

    $guard->check('rm -rf var/log/tmp');

    expect($guard->warningType('rm -rf var/log/tmp'))->toBe(CommandWarningType::DESTRUCTIVE_RM);
});
