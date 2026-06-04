<?php

declare(strict_types=1);

namespace App\Modules\Commands\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Commands\Models\Command;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\SSHManager;

final class CommandService
{
    private const SSH_TIMEOUT_SECONDS = 60;

    public function __construct(
        private readonly DangerousCommandGuard $dangerousCommandGuard,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {}

    public function run(Server $server, string $command, User $actor, Organization $org): Command
    {
        $this->dangerousCommandGuard->check($command);

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            $result = $connection->run($command, null, self::SSH_TIMEOUT_SECONDS);
            $output = $result->output();
            $exitCode = $result->exitCode;
        } finally {
            $connection->disconnect();
        }

        $record = Command::query()->create([
            'server_id' => (string) $server->getKey(),
            'organization_id' => (string) $org->getKey(),
            'user_id' => (string) $actor->getKey(),
            'command' => $command,
            'output' => $output,
            'exit_code' => $exitCode,
            'executed_at' => now(),
        ]);

        AuditLog::record(
            operation: 'command.executed',
            resource: $record,
            afterState: [
                'command_text' => $command,
                'exit_code' => $exitCode,
                'server_id' => (string) $server->getKey(),
            ],
        );

        return $record;
    }

    public function isWarnCommand(string $command): bool
    {
        return $this->dangerousCommandGuard->warn($command);
    }
}
