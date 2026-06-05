<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\PipelineBuilder;
use App\Packages\Execution\Steps\PHP\ReloadPHPFPMStep;
use App\Packages\Execution\Steps\PHP\RunMigrationsStep;
use App\Packages\Execution\Steps\Shared\ActivateReleaseStep;
use App\Packages\Execution\Steps\Shared\CloneRepositoryStep;
use App\Packages\Execution\Steps\Shared\ReloadServicesStep;
use App\Packages\Execution\Steps\Shared\SyncEnvVarsStep;
use App\Packages\Execution\Steps\Shared\VerifyConnectionStep;
use App\Packages\Execution\Steps\Shared\VerifyReleaseExistsStep;

it('builds rollback pipeline with verify, activate, and reload steps', function (): void {
    [, , $site] = executionFixture(Runtime::PHP);
    $steps = (new PipelineBuilder())->buildRollback($site);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    expect($names)->toBe([
        'verify-connection',
        'verify-release-exists',
        'activate-release',
        'reload-services',
    ])
        ->and($steps[0])->toBeInstanceOf(VerifyConnectionStep::class)
        ->and($steps[1])->toBeInstanceOf(VerifyReleaseExistsStep::class)
        ->and($steps[2])->toBeInstanceOf(ActivateReleaseStep::class)
        ->and($steps[3])->toBeInstanceOf(ReloadServicesStep::class);
});

it('places activate before php service reload steps', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    $activateIndex = array_search('activate-release', $names, true);
    $reloadIndex = array_search('reload-php-fpm', $names, true);
    $workersIndex = array_search('restart-workers', $names, true);

    expect($activateIndex)->toBeInt()->toBeLessThan($reloadIndex);
    expect($activateIndex)->toBeLessThan($workersIndex);
});

it('runs all pre-activation steps before activate for php', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);
    $activateIndex = array_search('activate-release', $names, true);

    $preActivation = [
        'verify-connection',
        'create-release-directory',
        'clone-repository',
        'install-composer-deps',
        'sync-env-vars',
        'link-shared-directories',
        'run-migrations',
        'clear-cache',
    ];

    foreach ($preActivation as $stepName) {
        expect(array_search($stepName, $names, true))->toBeLessThan($activateIndex);
    }
});

it('builds docker pull pipeline without git clone', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::PULL,
        'docker_image' => 'ghcr.io/helix/app:latest',
    ]);

    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    expect($names)->toBe([
        'verify-connection',
        'docker-login',
        'docker-pull',
        'sync-env-vars',
        'docker-compose-up',
        'docker-cleanup',
    ]);
});

it('builds docker build pipeline with clone', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::BUILD,
        'docker_image' => 'helix/app:latest',
    ]);

    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    expect($names[0])->toBe('verify-connection')
        ->and($names)->toContain('clone-repository')
        ->and($names)->toContain('docker-build')
        ->and($names)->not->toContain('docker-pull');
});

it('places sync-env-vars before link-shared-directories for php', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    $syncIndex = array_search('sync-env-vars', $names, true);
    $linkIndex = array_search('link-shared-directories', $names, true);
    $activateIndex = array_search('activate-release', $names, true);

    expect($syncIndex)->toBeInt()
        ->and($linkIndex)->toBeInt()
        ->and($syncIndex)->toBeLessThan($linkIndex)
        ->and($syncIndex)->toBeLessThan($activateIndex)
        ->and($steps[$syncIndex])->toBeInstanceOf(SyncEnvVarsStep::class);
});

it('includes sync-env-vars in docker pull pipeline before compose up', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::PULL,
        'docker_image' => 'ghcr.io/helix/app:latest',
    ]);

    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    $syncIndex = array_search('sync-env-vars', $names, true);
    $composeIndex = array_search('docker-compose-up', $names, true);

    expect($syncIndex)->toBeInt()
        ->and($composeIndex)->toBeInt()
        ->and($syncIndex)->toBeLessThan($composeIndex);
});

it('builds static git pipeline with asset build and nginx reload', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::STATIC);
    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    $preIndex = array_search('run-pre-deploy-script', $names, true);
    $activateIndex = array_search('activate-release', $names, true);
    $postIndex = array_search('run-post-deploy-script', $names, true);
    $nginxIndex = array_search('reload-nginx', $names, true);

    expect($names)->toContain('run-pre-deploy-script')
        ->and($names)->toContain('run-post-deploy-script')
        ->and($preIndex)->toBeInt()->toBeLessThan($activateIndex)
        ->and($postIndex)->toBeInt()->toBeGreaterThan($nginxIndex);
});

it('places pre-deploy hook before activation and post-deploy hook after service reload for php', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $steps = (new PipelineBuilder())->build($site, $deployment);
    $names = array_map(static fn ($step): string => $step->name(), $steps);

    $preIndex = array_search('run-pre-deploy-script', $names, true);
    $activateIndex = array_search('activate-release', $names, true);
    $workersIndex = array_search('restart-workers', $names, true);
    $postIndex = array_search('run-post-deploy-script', $names, true);

    expect($preIndex)->toBeInt()->toBeLessThan($activateIndex)
        ->and($postIndex)->toBeInt()->toBeGreaterThan($workersIndex);
});

it('php pipeline starts with verify and includes expected steps', function (): void {
    [, , $site, $deployment] = executionFixture(Runtime::PHP);
    $steps = (new PipelineBuilder())->build($site, $deployment);

    expect($steps[0])->toBeInstanceOf(VerifyConnectionStep::class)
        ->and($steps[1]->name())->toBe('create-release-directory')
        ->and($steps[2])->toBeInstanceOf(CloneRepositoryStep::class)
        ->and(collect($steps)->first(fn ($s) => $s instanceof ActivateReleaseStep))->not->toBeNull()
        ->and(collect($steps)->first(fn ($s) => $s instanceof RunMigrationsStep))->not->toBeNull()
        ->and(collect($steps)->first(fn ($s) => $s instanceof ReloadPHPFPMStep))->not->toBeNull();
});
