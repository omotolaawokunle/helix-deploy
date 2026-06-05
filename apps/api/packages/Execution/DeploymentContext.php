<?php

declare(strict_types=1);

namespace App\Packages\Execution;

use App\Modules\Deployments\Events\DeploymentLogLine;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Deployments\Models\DeploymentStep as DeploymentStepRecord;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use App\Modules\Deployments\Services\DeploymentCancellationService;
use App\Packages\Execution\Exceptions\DeploymentCancelledException;
use App\Packages\Execution\Exceptions\DeploymentStepFailedException;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\DB;

class DeploymentContext
{
    private const LOG_FLUSH_SIZE = 10;

    /** @var list<string> */
    private array $logBuffer = [];

    public readonly Deployment $deployment;

    public readonly Site $site;

    public readonly Server $server;

    public readonly SSHConnectionInterface $ssh;

    public readonly string $releasePath;

    public readonly string $sharedPath;

    public readonly string $currentPath;

    public readonly string $releaseId;

    public readonly ?string $repositoryCloneUrl;

    public ?DeploymentStepRecord $currentStepRecord = null;

    public ?string $executingStepName = null;

    public ?DeploymentCancellationService $cancellation = null;

    public function __construct(
        Deployment $deployment,
        Site $site,
        Server $server,
        SSHConnectionInterface $ssh,
        string $releasePath,
        string $sharedPath,
        string $currentPath,
        string $releaseId,
        ?string $repositoryCloneUrl = null,
    ) {
        $this->deployment = $deployment;
        $this->site = $site;
        $this->server = $server;
        $this->ssh = $ssh;
        $this->releasePath = $releasePath;
        $this->sharedPath = $sharedPath;
        $this->currentPath = $currentPath;
        $this->releaseId = $releaseId;
        $this->repositoryCloneUrl = $repositoryCloneUrl;
    }

    public static function forDeployment(
        Deployment $deployment,
        Site $site,
        Server $server,
        SSHConnectionInterface $ssh,
        ?string $releaseId = null,
        ?string $repositoryCloneUrl = null,
    ): self {
        $domain = $site->domain;
        $base = '/var/www/'.$domain;
        $releaseId ??= (string) $deployment->getKey();

        return new self(
            deployment: $deployment,
            site: $site,
            server: $server,
            ssh: $ssh,
            releasePath: $base.'/releases/'.$releaseId,
            sharedPath: $base.'/shared',
            currentPath: $base.'/current',
            releaseId: $releaseId,
            repositoryCloneUrl: $repositoryCloneUrl,
        );
    }

    public static function forRollback(
        Deployment $deployment,
        Site $site,
        Server $server,
        SSHConnectionInterface $ssh,
    ): self {
        $domain = $site->domain;
        $base = '/var/www/'.$domain;
        $releasePath = $deployment->release_path ?? $base.'/releases/unknown';

        return new self(
            deployment: $deployment,
            site: $site,
            server: $server,
            ssh: $ssh,
            releasePath: $releasePath,
            sharedPath: $base.'/shared',
            currentPath: $base.'/current',
            releaseId: basename($releasePath),
            repositoryCloneUrl: null,
        );
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
