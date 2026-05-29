<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Modules\Monitoring\Models\InfrastructureEvent;
use App\Modules\Servers\Enums\ServerStatus;
use App\Modules\Servers\Events\ServerConnected;
use App\Modules\Servers\Events\ServerConnectionFailed;
use App\Modules\Servers\Events\ServerFingerprintMismatch;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use App\Packages\SSH\SSHManager;
use App\Modules\Credentials\CredentialVault;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class VerifyServerConnectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $serverId,
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

    public function handle(SSHManager $sshManager, CredentialVault $vault): void
    {
        $server = $this->loadServer();
        if ($server === null) {
            return;
        }

        try {
            $connection = $sshManager->connectAndVerify($server, $vault);
            $probe = $connection->run(
                'echo "_ok_" && uname -a && (lsb_release -d 2>/dev/null | cut -f2 || true) && (php -v 2>/dev/null | head -1 || true) && (node -v 2>/dev/null || true)'
            )->throw();

            $parsedOutput = $this->parseProbeOutput($probe->stdout);

            $server->forceFill([
                'status' => ServerStatus::ACTIVE->value,
                'os' => $parsedOutput['os'],
                'php_version' => $parsedOutput['phpVersion'],
                'node_version' => $parsedOutput['nodeVersion'],
            ])->save();

            event(new ServerConnected($server->refresh()));

            InfrastructureEvent::query()->create([
                'organization_id' => (string) $server->organization_id,
                'server_id' => (string) $server->getKey(),
                'event_type' => 'server.connected',
                'payload' => [
                    'os' => $parsedOutput['os'],
                    'phpVersion' => $parsedOutput['phpVersion'],
                    'nodeVersion' => $parsedOutput['nodeVersion'],
                ],
                'created_at' => now(),
            ]);
        } catch (SSHFingerprintMismatchException $exception) {
            $server->forceFill([
                'status' => ServerStatus::DISCONNECTED->value,
            ])->save();

            event(new ServerFingerprintMismatch(
                server: $server->refresh(),
                expectedFingerprint: $exception->expectedFingerprint,
                receivedFingerprint: $exception->receivedFingerprint,
            ));

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
        $server = $this->loadServer();
        if ($server === null) {
            return;
        }

        if ($server->status === ServerStatus::CONNECTING) {
            $server->forceFill([
                'status' => ServerStatus::DISCONNECTED->value,
            ])->save();
        }

        event(new ServerConnectionFailed($server->refresh(), $exception->getMessage()));
    }

    private function loadServer(): ?Server
    {
        return Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with('credential')
            ->where(fn (Builder $query): Builder => $query->whereKey($this->serverId))
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

            if (preg_match('/^v\d+\.\d+\.\d+/', $line) === 1) {
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
