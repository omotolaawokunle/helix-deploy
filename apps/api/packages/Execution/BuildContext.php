<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Modules\BuildRunners\Models\BuildArtifact;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Deployments\Events\DeploymentLogLine;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep as DeploymentStepRecord;
use App\Modules\Deployments\Services\DeploymentCancellationService;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use App\Packages\Execution\Exceptions\DeploymentCancelledException;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\DB;

final class BuildContext
{
    private const LOG_FLUSH_SIZE = 10;

    /** @var list<string> */
    private array $logBuffer = [];

    public readonly Deployment $deployment;

    public readonly Site $site;

    public readonly BuildRunner $runner;

    public readonly SSHConnectionInterface $ssh;

    public readonly string $buildPath;

    public readonly string $artifactPath;

    public ?DeploymentStepRecord $currentStepRecord = null;

    public ?string $executingStepName = null;

    public ?DeploymentCancellationService $cancellation = null;

    public ?string $repositoryCloneUrl = null;

    public ?SSHConnectionInterface $targetSsh = null;

    public ?Server $targetServer = null;

    public ?BuildArtifact $artifact = null;

    public function __construct(
        Deployment $deployment,
        Site $site,
        BuildRunner $runner,
        SSHConnectionInterface $ssh,
        string $buildPath,
        string $artifactPath,
    ) {
        $this->deployment = $deployment;
        $this->site = $site;
        $this->runner = $runner;
        $this->ssh = $ssh;
        $this->buildPath = $buildPath;
        $this->artifactPath = $artifactPath;
    }

    public static function forDeployment(
        Deployment $deployment,
        Site $site,
        BuildRunner $runner,
        SSHConnectionInterface $ssh,
        ?string $repositoryCloneUrl = null,
    ): self {
        $deploymentId = (string) $deployment->getKey();

        $context = new self(
            deployment: $deployment,
            site: $site,
            runner: $runner,
            ssh: $ssh,
            buildPath: '/builds/'.$deploymentId.'/',
            artifactPath: '/tmp/'.$deploymentId.'.tar.gz',
        );
        $context->repositoryCloneUrl = $repositoryCloneUrl;

        return $context;
    }

    public function log(string $line): void
    {
        $this->logBuffer[] = $line;
        event(new DeploymentLogLine(
            $this->deployment,
            $line,
            $this->currentStepRecord !== null ? (string) $this->currentStepRecord->getKey() : null,
        ));

        if (count($this->logBuffer) >= self::LOG_FLUSH_SIZE) {
            $this->flushLog();
        }
    }

    public function flushLog(): void
    {
        if ($this->currentStepRecord === null || $this->logBuffer === []) {
            $this->logBuffer = [];

            return;
        }

        $chunk = implode("\n", $this->logBuffer)."\n";
        $quoted = DB::connection()->getPdo()->quote($chunk);

        $this->currentStepRecord->newQueryWithoutScopes()
            ->whereKey($this->currentStepRecord->getKey())
            ->update([
                'output' => DB::raw("COALESCE(output, '') || {$quoted}"),
            ]);

        $existing = (string) $this->currentStepRecord->getAttribute('output');
        $this->currentStepRecord->setAttribute('output', $existing.$chunk);
        $this->logBuffer = [];
    }

    public function assertNotCancelled(): void
    {
        if ($this->cancellation === null) {
            return;
        }

        if ($this->cancellation->isRequested((string) $this->deployment->getKey())) {
            $this->ssh->interrupt();
            throw new DeploymentCancelledException();
        }
    }

    public function run(string $command, ?int $timeout = null): SSHResult
    {
        $this->assertNotCancelled();

        $result = $this->ssh->run(
            $command,
            function (string $line): void {
                $this->assertNotCancelled();
                $this->log($line);
            },
            $timeout,
        );

        $this->assertNotCancelled();

        if ($result->failed()) {
            $stepName = $this->executingStepName
                ?? $this->currentStepRecord?->name
                ?? 'unknown';

            throw new DeploymentStepFailedException(
                sprintf('[%s] command failed: %s', $stepName, $result->output()),
                $result,
                $stepName,
            );
        }

        return $result;
    }
}
