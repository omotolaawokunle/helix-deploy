<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Events\DeploymentLogLine;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\BuildContext;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\Build\BuildAssetsBuildStep;
use App\Packages\Execution\Steps\PHP\BuildAssetsStep;
use App\Packages\Execution\Steps\PHP\ClearCacheStep;
use App\Packages\Execution\Steps\PHP\InstallComposerDepsStep;
use App\Packages\Execution\Steps\PHP\InstallNpmDepsStep;
use App\Packages\Execution\Steps\PHP\ReloadPHPFPMStep;
use App\Packages\Execution\Steps\PHP\RestartWorkersStep;
use App\Packages\Execution\Steps\PHP\RunMigrationsStep;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('install composer deps runs composer install', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*composer install*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new InstallComposerDepsStep)->run($ctx);

    $ssh->assertCommandExecuted('*composer install --no-dev*');
});

it('install npm deps is skippable without package json', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['test -f *' => sshFailure()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);
    $step = new InstallNpmDepsStep;

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

    (new InstallNpmDepsStep)->run($ctx);

    $ssh->assertCommandExecuted('*npm ci*');
});

it('build assets is skippable without package json', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['test -f *' => sshFailure()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect((new BuildAssetsStep)->isSkippable($ctx))->toBeTrue();
});

it('build assets uses a 90 second ssh timeout', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*npm run build*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new BuildAssetsStep)->run($ctx);

    $ssh->assertCommandTimeout('*npm run build*', 90);
});

it('runner build assets uses a 90 second ssh timeout', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $owner = User::query()->findOrFail($deployment->triggered_by);
    $runner = BuildRunner::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $deployment->organization_id,
        'name' => 'assets-runner-'.Str::random(4),
        'ip_address' => '10.0.0.80',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'status' => BuildRunnerStatus::ONLINE->value,
        'max_concurrent_builds' => 1,
        'supported_runtimes' => ['php'],
        'created_by' => (string) $owner->getKey(),
    ]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*npm run build*' => sshSuccess()]);
    $ctx = BuildContext::forDeployment($deployment, $site, $runner, $ssh);

    (new BuildAssetsBuildStep)->run($ctx);

    $ssh->assertCommandTimeout('*npm run build*', 90);
});

it('run migrations is skippable when site flag is false', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $site->forceFill(['run_migrations' => false])->save();
    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new RunMigrationsStep)->isSkippable($ctx))->toBeTrue();
});

it('run migrations logs production warning and runs artisan migrate', function (): void {
    Event::fake([DeploymentLogLine::class]);
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*php artisan migrate*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RunMigrationsStep)->run($ctx);

    $ssh->assertCommandExecuted('*php artisan migrate --force --no-interaction*');
    $ssh->assertCommandExecuted('*create database if not exists*');
    $ssh->assertCommandExecuted('*pg_database*');
    Event::assertDispatched(DeploymentLogLine::class, function ($event): bool {
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

    (new ClearCacheStep)->run($ctx);

    expect($ssh->getExecutedCommands())->toHaveCount(3);
});

it('reload php fpm uses site php version', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*systemctl reload php8.3-fpm*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ReloadPHPFPMStep)->run($ctx);

    $ssh->assertCommandExecuted('*systemctl reload php8.3-fpm*');
});

it('restart workers is skippable on first deploy', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new RestartWorkersStep)->isSkippable($ctx))->toBeTrue();
});

it('restart workers is skippable when only prior failed deploys exist', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture(Runtime::PHP);

    Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::FAILED,
        'triggered_by' => (string) $deployment->triggered_by,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new RestartWorkersStep)->isSkippable($ctx))->toBeTrue();
});

it('restart workers is not skippable when a prior successful deploy exists', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture(Runtime::PHP);

    Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::SUCCESS,
        'triggered_by' => (string) $deployment->triggered_by,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new RestartWorkersStep)->isSkippable($ctx))->toBeFalse();
});

it('restart workers uses horizon terminate when horizon is installed', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'test -d *vendor/laravel/horizon*' => sshSuccess(),
        '*horizon:terminate*' => sshSuccess(),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RestartWorkersStep)->run($ctx);

    $ssh->assertCommandExecuted('*horizon:terminate*');
    $ssh->assertCommandNotExecuted('*queue:restart*');
});

it('restart workers falls back to queue restart without horizon', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'test -d *vendor/laravel/horizon*' => sshFailure(),
        '*queue:restart*' => sshSuccess(),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RestartWorkersStep)->run($ctx);

    $ssh->assertCommandExecuted('*queue:restart*');
    $ssh->assertCommandNotExecuted('*horizon:terminate*');
});

it('php step failure throws deployment step failed with ssh result', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PHP);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*composer install*' => sshFailure('composer error')]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    try {
        (new InstallComposerDepsStep)->run($ctx);
        expect(false)->toBeTrue();
    } catch (DeploymentStepFailedException $exception) {
        expect($exception->result->stderr)->toBe('composer error')
            ->and($exception->stepName)->toBe('install-composer-deps');
    }
});
