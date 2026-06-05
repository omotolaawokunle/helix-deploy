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

    (new BuildStaticAssetsStep())->run($ctx);

    expect($ssh->getExecutedCommands())->toHaveCount(1)
        ->and($ssh->getExecutedCommands()[0])->toContain('test -f');
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
