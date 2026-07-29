<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Models\Site;

it('allows auto deploy for git deploy mode sites', function (): void {
    $site = new Site([
        'deploy_mode' => DeployMode::GIT,
        'repository_url' => null,
        'deploy_branch' => 'main',
    ]);

    expect($site->canAutoDeploy())->toBeTrue();
});

it('allows auto deploy for docker build mode sites with repository and branch', function (): void {
    $site = new Site([
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::BUILD,
        'repository_url' => 'git@github.com:helix/example.git',
        'deploy_branch' => 'main',
    ]);

    expect($site->canAutoDeploy())->toBeTrue();
});

it('rejects auto deploy for docker pull mode sites', function (): void {
    $site = new Site([
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::PULL,
        'repository_url' => 'git@github.com:helix/example.git',
        'deploy_branch' => 'main',
    ]);

    expect($site->canAutoDeploy())->toBeFalse();
});

it('rejects auto deploy for docker build mode sites without repository', function (): void {
    $site = new Site([
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::BUILD,
        'repository_url' => null,
        'deploy_branch' => 'main',
    ]);

    expect($site->canAutoDeploy())->toBeFalse();
});

it('rejects auto deploy for docker build mode sites without deploy branch', function (): void {
    $site = new Site([
        'deploy_mode' => DeployMode::DOCKER,
        'docker_build_mode' => DockerBuildMode::BUILD,
        'repository_url' => 'git@github.com:helix/example.git',
        'deploy_branch' => '',
    ]);

    expect($site->canAutoDeploy())->toBeFalse();
});
