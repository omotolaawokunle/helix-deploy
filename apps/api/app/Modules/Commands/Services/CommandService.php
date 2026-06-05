<?php

declare(strict_types=1);

namespace App\Modules\Commands\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Commands\Enums\CommandStatus;
use App\Modules\Commands\Events\CommandCompleted;
use App\Modules\Commands\Events\CommandLogLine;
use App\Modules\Commands\Exceptions\CommandCancelledException;
use App\Modules\Commands\Models\Command;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Exceptions\SSHTimeoutException;
use App\Packages\SSH\SSHManager;
use Illuminate\Support\Facades\DB;

final class CommandService
{
    private const DEFAULT_TIMEOUT_SECONDS = 60;

    public function __construct(
        private readonly DangerousCommandGuard $dangerousCommandGuard,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
        private readonly CommandCancellationService $cancellationService,
    ) {}

    public function queue(
        Server $server,
        string $commandText,
        User $actor,
        Organization $org,
        ?int $timeoutSeconds = null,
    ): Command {
        $this->dangerousCommandGuard->check($commandText);

        return Command::query()->create([
            'server_id' => (string) $server->getKey(),
            'organization_id' => (string) $org->getKey(),
            'user_id' => (string) $actor->getKey(),
            'command' => $commandText,
            'status' => CommandStatus::PENDING,
            'timeout_seconds' => $timeoutSeconds ?? self::DEFAULT_TIMEOUT_SECONDS,
            'executed_at' => now(),
        ]);
    }

    public function execute(Command $command): Command
    {
        $command = $command->fresh(['server']);

        if ($command === null || $command->status !== CommandStatus::PENDING) {
            return $command ?? throw new \RuntimeException('Command not found.');
        }

        if ($this->cancellationService->isRequested((string) $command->getKey())) {
            $command->forceFill([
                'status' => CommandStatus::CANCELLED,
                'finished_at' => now(),
            ])->save();

            event(new CommandCompleted($command->refresh()));

            return $command;
        }

        $server = $command->server;
        abort_if($server === null, 404, 'Command server not found.');

        $command->forceFill([
            'status' => CommandStatus::RUNNING,
            'started_at' => now(),
        ])->save();

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();
        $exitCode = null;

        try {
            $result = $connection->run(
                $command->command,
                function (string $line) use ($command, $connection): void {
                    $this->appendOutput($command, $line);
                    event(new CommandLogLine($command->refresh(), $line));

                    if ($this->cancellationService->isRequested((string) $command->getKey())) {
                        $connection->interrupt();
                        throw new CommandCancelledException();
                    }
                },
                (int) $command->timeout_seconds,
            );

            $exitCode = $result->exitCode;
            $command->forceFill([
                'status' => CommandStatus::COMPLETED,
                'exit_code' => $exitCode,
                'finished_at' => now(),
            ])->save();
        } catch (CommandCancelledException) {
            $command->forceFill([
                'status' => CommandStatus::CANCELLED,
                'finished_at' => now(),
            ])->save();
        } catch (SSHTimeoutException) {
            $connection->interrupt();
            $command->forceFill([
                'status' => CommandStatus::FAILED,
                'exit_code' => 124,
                'finished_at' => now(),
            ])->save();
        } finally {
            $connection->disconnect();
            $this->cancellationService->clear((string) $command->getKey());
        }

        $command = $command->refresh();

        AuditLog::record(
            operation: 'command.executed',
            resource: $command,
            afterState: [
                'command_text' => $command->command,
                'exit_code' => $command->exit_code,
                'server_id' => (string) $command->server_id,
                'status' => $command->status->value,
            ],
        );

        event(new CommandCompleted($command));

        return $command;
    }

    public function isWarnCommand(string $command): bool
    {
        return $this->dangerousCommandGuard->warn($command);
    }

    private function appendOutput(Command $command, string $line): void
    {
        $chunk = $line."\n";
        $quoted = DB::connection()->getPdo()->quote($chunk);

        $command->newQueryWithoutScopes()
            ->whereKey($command->getKey())
            ->update([
                'output' => DB::raw("COALESCE(output, '') || {$quoted}"),
            ]);

        $existing = (string) ($command->output ?? '');
        $command->setAttribute('output', $existing.$chunk);
    }
}
