<?php

declare(strict_types=1);

use App\Modules\Sites\Enums\Runtime;
use App\Packages\Execution\Steps\Static\BuildStaticAssetsStep;
use App\Packages\SSH\FakeSSHConnection;

it('skips static asset build when package.json is missing', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture(Runtime::STATIC);
    $ssh = (new FakeSSHConnection())->connect();
    $ssh->addSequence('test -f *', sshFailure());

    $ctx = executionContext($site, $deployment, $server, $ssh);

    expect((new BuildStaticAssetsStep())->isSkippable($ctx))->toBeTrue();
});

it('skips static asset build when build assets is disabled', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture(Runtime::STATIC);
    $site->forceFill(['build_assets' => false])->save();
    $ctx = executionContext($site, $deployment, $server, fakeSsh());

    expect((new BuildStaticAssetsStep())->isSkippable($ctx))->toBeTrue();
});

it('runs npm build when package.json exists', function (): void {
    [$organization, $server, $site, $deployment] = executionFixture(Runtime::STATIC);
    $ssh = (new FakeSSHConnection())->connect();
    $ssh->addSequence('test -f *', sshSuccess());
    $ssh->addSequence('*npm ci*', sshSuccess());

    $ctx = executionContext($site, $deployment, $server, $ssh);

    (new BuildStaticAssetsStep())->run($ctx);

    $ssh->assertCommandExecuted('*npm ci*');
});
