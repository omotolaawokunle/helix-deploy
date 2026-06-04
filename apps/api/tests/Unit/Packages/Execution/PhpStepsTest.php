<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\PHP\BuildAssetsStep;
use App\Packages\Execution\Steps\PHP\ClearCacheStep;
use App\Packages\Execution\Steps\PHP\InstallComposerDepsStep;
use App\Packages\Execution\Steps\PHP\InstallNpmDepsStep;
use App\Packages\Execution\Steps\PHP\ReloadPHPFPMStep;
use App\Packages\Execution\Steps\PHP\RestartWorkersStep;
use App\Packages\Execution\Steps\PHP\RunMigrationsStep;
use Illuminate\Support\Facades\Event;

it('install composer deps runs composer install', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*composer install*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new InstallComposerDepsStep())->run($ctx);

    $ssh->assertCommandExecuted('*composer install --no-dev*');
});

it('install npm deps is skippable without package json', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['test -f *' => sshFailure()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);
    $step = new InstallNpmDepsStep();

    expect($step->isSkippable($ctx))->toBeTrue();
});

it('install npm deps runs npm ci when package json exists', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'test -f *' => sshSuccess(),
        '*npm ci*' => sshSuccess(),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new InstallNpmDepsStep())->run($ctx);

    $ssh->assertCommandExecuted('*npm ci*');
});

it('build assets is skippable without package json', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['test -f *' => sshFailure()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect((new BuildAssetsStep())->isSkippable($ctx))->toBeTrue();
});

it('run migrations is skippable when site flag is false', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $site->forceFill(['run_migrations' => false])->save();
    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new RunMigrationsStep())->isSkippable($ctx))->toBeTrue();
});

it('run migrations logs production warning and runs artisan migrate', function (): void {
    Event::fake([\App\Modules\Deployments\Events\DeploymentLogLine::class]);
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*php artisan migrate*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RunMigrationsStep())->run($ctx);

    $ssh->assertCommandExecuted('*php artisan migrate --force --no-interaction*');
    Event::assertDispatched(\App\Modules\Deployments\Events\DeploymentLogLine::class, function ($event): bool {
        return str_contains($event->line, 'WARNING: running database migrations on production');
    });
});

it('clear cache runs artisan cache commands', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        '*config:cache*' => sshSuccess(),
        '*route:cache*' => sshSuccess(),
        '*view:cache*' => sshSuccess(),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ClearCacheStep())->run($ctx);

    expect($ssh->getExecutedCommands())->toHaveCount(3);
});

it('reload php fpm uses site php version', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*systemctl reload php8.3-fpm*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ReloadPHPFPMStep())->run($ctx);

    $ssh->assertCommandExecuted('*systemctl reload php8.3-fpm*');
});

it('restart workers is skippable without horizon config', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['test -f *' => sshFailure()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect((new RestartWorkersStep())->isSkippable($ctx))->toBeTrue();
});

it('php step failure throws deployment step failed with ssh result', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*composer install*' => sshFailure('composer error')]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    try {
        (new InstallComposerDepsStep())->run($ctx);
        expect(false)->toBeTrue();
    } catch (DeploymentStepFailedException $exception) {
        expect($exception->result->stderr)->toBe('composer error')
            ->and($exception->stepName)->toBe('install-composer-deps');
    }
});
