<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\Docker\DockerBuildStep;
use App\Packages\Execution\Steps\Docker\DockerCleanupStep;
use App\Packages\Execution\Steps\Docker\DockerComposeUpStep;
use App\Packages\Execution\Steps\Docker\DockerLoginStep;
use App\Packages\Execution\Steps\Docker\DockerPullStep;
use App\Packages\Execution\Steps\Go\DownloadBinaryStep;
use App\Packages\Execution\Steps\Go\ReplaceBinaryStep;
use App\Packages\Execution\Steps\Go\RestartGoServiceStep;
use App\Packages\Execution\Steps\NodeJS\BuildNodeAssetsStep;
use App\Packages\Execution\Steps\NodeJS\InstallNpmDepsNodeStep;
use App\Packages\Execution\Steps\NodeJS\ReloadPM2Step;
use App\Packages\Execution\Steps\Python\CollectStaticStep;
use App\Packages\Execution\Steps\Python\InstallPythonDepsStep;
use App\Packages\Execution\Steps\Python\ReloadPythonProcessStep;

it('node install npm runs npm ci', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::NODEJS);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*npm ci*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new InstallNpmDepsNodeStep())->run($ctx);

    $ssh->assertCommandExecuted('*npm ci*');
});

it('node build assets runs npm run build', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::NODEJS);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*npm run build*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new BuildNodeAssetsStep())->run($ctx);

    $ssh->assertCommandExecuted('*npm run build*');
});

it('reload pm2 uses site domain', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::NODEJS);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['pm2 reload *' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ReloadPM2Step())->run($ctx);

    $ssh->assertCommandExecuted('pm2 reload *app.example.test*');
});

it('python install deps runs pip install', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PYTHON);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*pip install*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new InstallPythonDepsStep())->run($ctx);

    $ssh->assertCommandExecuted('*pip install -r requirements.txt*');
});

it('collect static is skippable without manage.py', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PYTHON);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['test -f *' => sshFailure()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect((new CollectStaticStep())->isSkippable($ctx))->toBeTrue();
});

it('collect static runs manage.py collectstatic', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PYTHON);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*collectstatic*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new CollectStaticStep())->run($ctx);

    $ssh->assertCommandExecuted('*collectstatic --noinput*');
});

it('reload python process reloads gunicorn service', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::PYTHON);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*systemctl reload*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ReloadPythonProcessStep())->run($ctx);

    $ssh->assertCommandExecuted('*gunicorn-app.example.test*');
});

it('go download binary runs go build', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::GO);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*go build*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new DownloadBinaryStep())->run($ctx);

    $ssh->assertCommandExecuted('*go build -o*');
});

it('go replace binary copies built artifact', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::GO);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['cp *' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ReplaceBinaryStep())->run($ctx);

    $ssh->assertCommandExecuted('cp *');
});

it('go restart service uses configured service name', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::GO);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*systemctl restart*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RestartGoServiceStep())->run($ctx);

    $ssh->assertCommandExecuted('*systemctl restart*app.example.test*');
});

it('docker login runs when registry is configured', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_registry' => 'ghcr.io',
    ]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['docker login *' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new DockerLoginStep())->run($ctx);

    $ssh->assertCommandExecuted('docker login *ghcr.io*');
});

it('docker build runs docker build in release path', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_image' => 'helix/app:latest',
    ]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['docker build *' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new DockerBuildStep())->run($ctx);

    $ssh->assertCommandExecuted('docker build -t *helix/app:latest*');
});

it('docker login is skippable without registry', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_registry' => null,
    ]);
    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new DockerLoginStep())->isSkippable($ctx))->toBeTrue();
});

it('docker pull runs docker pull with image', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_image' => 'ghcr.io/helix/app:latest',
    ]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['docker pull *' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new DockerPullStep())->run($ctx);

    $ssh->assertCommandExecuted('docker pull *ghcr.io/helix/app:latest*');
});

it('docker compose up runs compose in shared directory', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_compose_path' => '/var/www/app.example.test/shared/docker-compose.yml',
    ]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['*docker compose*' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new DockerComposeUpStep())->run($ctx);

    $ssh->assertCommandExecuted('*docker compose*up -d*');
});

it('docker cleanup prunes images and containers', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, ['deploy_mode' => DeployMode::DOCKER]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'docker image prune*' => sshSuccess(),
        'docker container prune*' => sshSuccess(),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new DockerCleanupStep())->run($ctx);

    expect($ssh->getExecutedCommands())->toHaveCount(2);
});

it('docker pull failure throws deployment step failed exception', function (): void {
    [, $server, $site, $deployment] = executionFixture(Runtime::DOCKER, [
        'deploy_mode' => DeployMode::DOCKER,
        'docker_image' => 'ghcr.io/helix/app:latest',
    ]);
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['docker pull *' => sshFailure('denied')]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect(fn () => (new DockerPullStep())->run($ctx))
        ->toThrow(DeploymentStepFailedException::class);
});
