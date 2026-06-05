<?php

declare(strict_types=1);

use App\Modules\Deployments\Services\DeploymentCancellationService;
use App\Packages\Execution\Contracts\DeploymentStepInterface;
use App\Packages\Execution\DeploymentContext;
use App\Packages\Execution\DeploymentRunner;
use App\Packages\Execution\Exceptions\DeploymentCancelledException;
use App\Packages\SSH\FakeSSHConnection;
use Illuminate\Support\Facades\Cache;

it('stops pipeline and interrupts ssh when cancellation is requested', function (): void {
    Cache::flush();

    [, $server, $site, $deployment] = executionFixture();
    $ssh = (new FakeSSHConnection())->connect();
    $ctx = executionContext($site, $deployment, $server, $ssh);

    $cancellation = new DeploymentCancellationService();
    $cancellation->request((string) $deployment->getKey());

    $step = new class implements DeploymentStepInterface
    {
        public function name(): string
        {
            return 'noop-step';
        }

        public function run(DeploymentContext $ctx): void
        {
        }

        public function rollback(DeploymentContext $ctx): void
        {
        }

        public function isSkippable(DeploymentContext $ctx): bool
        {
            return false;
        }
    };

    $runner = new DeploymentRunner($cancellation);

    try {
        $runner->run($ctx, [$step]);
        expect(false)->toBeTrue('Expected DeploymentCancelledException');
    } catch (DeploymentCancelledException) {
        expect($ssh->interrupted)->toBeTrue();
    }
});
