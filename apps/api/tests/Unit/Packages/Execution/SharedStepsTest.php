<?php

declare(strict_types=1);

use App\Modules\Deployments\Models\Release;
use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\Execution\Steps\Shared\ActivateReleaseStep;
use App\Packages\Execution\Steps\Shared\CleanupOldReleasesStep;
use App\Packages\Execution\Steps\Shared\CloneRepositoryStep;
use App\Packages\Execution\Steps\Shared\CreateReleaseDirectoryStep;
use App\Packages\Execution\Steps\Shared\LinkSharedDirectoriesStep;
use App\Packages\Execution\Steps\Shared\RunPostDeployScriptStep;
use App\Packages\Execution\Steps\Shared\RunPreDeployScriptStep;
use App\Packages\Execution\Steps\Shared\VerifyConnectionStep;
use Illuminate\Support\Facades\Event;

it('verify connection runs echo and df in order', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'echo "_ok_"' => sshSuccess('_ok_'),
        'df -h / *' => sshSuccess("15G\n"),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new VerifyConnectionStep())->run($ctx);

    expect($ssh->getExecutedCommands()[0])->toBe('echo "_ok_"')
        ->and($ssh->getExecutedCommands()[1])->toContain('df -h /');
});

it('verify connection throws when disk space below 200mb', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'echo "_ok_"' => sshSuccess('_ok_'),
        'df -h / *' => sshSuccess("100M\n"),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect(fn () => (new VerifyConnectionStep())->run($ctx))
        ->toThrow(DeploymentStepFailedException::class);
});

it('verify connection logs warning below 1gb', function (): void {
    Event::fake([\App\Modules\Deployments\Events\DeploymentLogLine::class]);
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'echo "_ok_"' => sshSuccess('_ok_'),
        'df -h / *' => sshSuccess("800M\n"),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new VerifyConnectionStep())->run($ctx);

    Event::assertDispatched(\App\Modules\Deployments\Events\DeploymentLogLine::class, function ($event): bool {
        return str_contains($event->line, 'WARNING: available disk space is below 1GB');
    });
});

it('verify connection throws deployment step failed on ssh failure', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'echo "_ok_"' => sshFailure('connection refused'),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    try {
        (new VerifyConnectionStep())->run($ctx);
        expect(false)->toBeTrue('expected exception');
    } catch (DeploymentStepFailedException $exception) {
        expect($exception->result->failed())->toBeTrue()
            ->and($exception->stepName)->toBe('verify-connection');
    }
});

it('create release directory creates release record and mkdir', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'mkdir -p *' => [sshSuccess(), sshSuccess()],
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new CreateReleaseDirectoryStep())->run($ctx);

    $ssh->assertCommandExecuted('mkdir -p */releases/*');
    expect(Release::query()->where('deployment_id', $deployment->getKey())->exists())->toBeTrue();
});

it('clone repository runs git commands in order and updates deployment', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    $releasePath = '/var/www/app.example.test/releases/'.$deployment->getKey();
    queueSshResponses($ssh, [
        'git clone *' => sshSuccess(),
        'git -C * rev-parse HEAD' => sshSuccess('abc123def'),
        'git -C * log -1 *' => sshSuccess('Initial commit'),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new CloneRepositoryStep())->run($ctx);

    $commands = $ssh->getExecutedCommands();
    expect($commands[0])->toContain('git clone --depth=1')
        ->and($commands[0])->toContain('--branch=')
        ->and($commands[1])->toContain('rev-parse HEAD')
        ->and($deployment->refresh()->commit_hash)->toBe('abc123def')
        ->and($deployment->commit_message)->toBe('Initial commit');
});

it('activate release uses ln -sfn and verifies readlink', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    $releasePath = '/var/www/app.example.test/releases/'.$deployment->getKey();
    queueSshResponses($ssh, [
        'ln -sfn *' => sshSuccess(),
        'readlink -f *' => sshSuccess($releasePath),
    ]);
    Release::query()->create([
        'site_id' => (string) $site->getKey(),
        'deployment_id' => (string) $deployment->getKey(),
        'organization_id' => (string) $site->organization_id,
        'path' => $releasePath,
        'commit_hash' => 'abc',
        'is_active' => false,
        'created_at' => now(),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new ActivateReleaseStep())->run($ctx);

    expect($ssh->getExecutedCommands()[0])->toContain('ln -sfn')
        ->and($ssh->getExecutedCommands()[0])->not->toContain('ln -s ')
        ->and(Release::query()->where('path', $releasePath)->value('is_active'))->toBeTrue();
});

it('activate release throws when readlink does not match release path', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'ln -sfn *' => sshSuccess(),
        'readlink -f *' => sshSuccess('/var/www/app.example.test/releases/old'),
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect(fn () => (new ActivateReleaseStep())->run($ctx))
        ->toThrow(DeploymentStepFailedException::class);
});

it('cleanup old releases never deletes active release', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    $active = '/var/www/app.example.test/releases/active';
    $old1 = '/var/www/app.example.test/releases/old-1';
    $old2 = '/var/www/app.example.test/releases/old-2';
    config(['helixdeploy.release_retention' => 1]);

    queueSshResponses($ssh, [
        'ls -1dt *' => sshSuccess(implode("\n", [$active, $old1, $old2])),
        'readlink -f *' => sshSuccess($active),
        'rm -rf *' => [sshSuccess(), sshSuccess()],
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new CleanupOldReleasesStep())->run($ctx);

    $deleted = array_filter($ssh->getExecutedCommands(), static fn (string $cmd): bool => str_contains($cmd, 'rm -rf'));
    expect($deleted)->toHaveCount(2);
    foreach ($deleted as $command) {
        expect($command)->not->toContain('active');
    }
});

it('link shared directories symlinks env and storage', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $ssh = fakeSsh();
    queueSshResponses($ssh, [
        'ln -sfn *' => [sshSuccess(), sshSuccess()],
    ]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new LinkSharedDirectoriesStep())->run($ctx);

    expect($ssh->getExecutedCommands())->toHaveCount(2)
        ->and($ssh->getExecutedCommands()[0])->toContain('ln -sfn')
        ->and($ssh->getExecutedCommands()[0])->toContain('/shared/.env');
});

it('run pre-deploy script is skippable when script is null', function (): void {
    [, , $site] = executionFixture();
    $site->forceFill(['pre_deploy_script' => null])->save();
    $ssh = fakeSsh();
    [, $server, , $deployment] = executionFixture();
    $ctx = executionContext($site, $deployment, $server, $ssh);
    $step = new RunPreDeployScriptStep();

    expect($step->isSkippable($ctx))->toBeTrue();
    $step->run($ctx);
    $ssh->assertCommandCount(0);
});

it('run pre-deploy script executes when script is set', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $site->forceFill(['pre_deploy_script' => 'php artisan deploy:pre'])->save();
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['cd * && php artisan deploy:pre' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RunPreDeployScriptStep())->run($ctx);

    $ssh->assertCommandExecuted('cd */releases/* && php artisan deploy:pre');
});

it('run post-deploy script is skippable when script is null', function (): void {
    [, , $site] = executionFixture();
    $site->forceFill(['post_deploy_script' => null])->save();
    $ssh = fakeSsh();
    [, $server, , $deployment] = executionFixture();
    $ctx = executionContext($site, $deployment, $server, $ssh);
    $step = new RunPostDeployScriptStep();

    expect($step->isSkippable($ctx))->toBeTrue();
    $step->run($ctx);
    $ssh->assertCommandCount(0);
});

it('run post-deploy script executes when script is set', function (): void {
    [, $server, $site, $deployment] = executionFixture();
    $site->forceFill(['post_deploy_script' => 'php artisan deploy:hook'])->save();
    $ssh = fakeSsh();
    queueSshResponses($ssh, ['cd * && php artisan deploy:hook' => sshSuccess()]);
    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new RunPostDeployScriptStep())->run($ctx);

    $ssh->assertCommandExecuted('cd */releases/* && php artisan deploy:hook');
});
