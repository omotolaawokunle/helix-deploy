<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Jobs;

use App\Modules\BuildRunners\Actions\ReportBuildRunnerFingerprintMismatchAction;
use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\BuildRunners\Events\BuildRunnerOffline;
use App\Modules\BuildRunners\Events\BuildRunnerOnline;
use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Monitoring\Models\InfrastructureEvent;
use App\Packages\SSH\BuildRunnerSSHManager;
use App\Packages\SSH\Exceptions\BuildRunnerSSHFingerprintMismatchException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class VerifyBuildRunnerConnectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $runnerId,
    ) {
        $this->onQueue('monitoring');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(
        BuildRunnerSSHManager $sshManager,
        CredentialVault $vault,
        ReportBuildRunnerFingerprintMismatchAction $reportFingerprintMismatch,
    ): void {
        $runner = $this->loadRunner();
        if ($runner === null) {
            return;
        }

        try {
            $connection = $sshManager->connectAndVerify($runner, $vault);
            $probe = $connection->run(
                'echo "_ok_" && uname -a && (node -v 2>/dev/null || true) && (php -v 2>/dev/null | head -1 || true)'
            )->throw();

            $parsedOutput = $this->parseProbeOutput($probe->stdout);

            $runner->forceFill([
                'status' => BuildRunnerStatus::ONLINE->value,
            ])->save();

            event(new BuildRunnerOnline($runner->refresh()));

            InfrastructureEvent::query()->create([
                'organization_id' => (string) $runner->organization_id,
                'server_id' => null,
                'event_type' => 'build_runner.online',
                'payload' => [
                    'runnerId' => (string) $runner->getKey(),
                    'os' => $parsedOutput['os'],
                    'phpVersion' => $parsedOutput['phpVersion'],
                    'nodeVersion' => $parsedOutput['nodeVersion'],
                ],
                'created_at' => now(),
            ]);
        } catch (BuildRunnerSSHFingerprintMismatchException $exception) {
            $reportFingerprintMismatch->execute($exception);

            return;
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            if (isset($connection)) {
                $connection->disconnect();
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $runner = $this->loadRunner();
        if ($runner === null) {
            return;
        }

        if ($runner->status === BuildRunnerStatus::CONNECTING) {
            $runner->forceFill([
                'status' => BuildRunnerStatus::OFFLINE->value,
            ])->save();
        }

        event(new BuildRunnerOffline($runner->refresh(), $exception->getMessage()));
    }

    private function loadRunner(): ?BuildRunner
    {
        return BuildRunner::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('credential')
            ->where(fn (Builder $query): Builder => $query->whereKey($this->runnerId))
            ->first();
    }

    /**
     * @return array{os: string|null, phpVersion: string|null, nodeVersion: string|null}
     */
    private function parseProbeOutput(string $output): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: [])));

        if ($lines !== [] && $lines[0] === '_ok_') {
            array_shift($lines);
        }

        $os = $lines[0] ?? null;
        $phpVersion = null;
        $nodeVersion = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'PHP ')) {
                $phpVersion = $line;
                continue;
            }

            if (preg_match('/^v?\d+\.\d+\.\d+/', $line) === 1) {
                $nodeVersion = $line;
            }
        }

        return [
            'os' => $os,
            'phpVersion' => $phpVersion,
            'nodeVersion' => $nodeVersion,
        ];
    }
}
