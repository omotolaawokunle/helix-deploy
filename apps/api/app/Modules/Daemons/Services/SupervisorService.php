<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Daemons\Enums\DaemonStatus;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHResult;

class SupervisorService
{
    public function __construct(
        private readonly SupervisorConfigGenerator $configGenerator,
    ) {
    }

    public function create(Server $server, SSHConnectionInterface $ssh, CreateDaemonDTO $dto, User $actor): SupervisorProcess
    {
        $configPath = '/etc/supervisor/conf.d/'.$dto->name.'.conf';

        $daemon = SupervisorProcess::query()->create([
            'server_id' => (string) $server->getKey(),
            'organization_id' => (string) $server->organization_id,
            'name' => $dto->name,
            'command' => $dto->command,
            'directory' => $dto->directory,
            'user' => $dto->user,
            'processes' => $dto->processes,
            'status' => DaemonStatus::STOPPED,
            'config_path' => $configPath,
            'created_by' => (string) $actor->getKey(),
        ]);

        $config = $this->configGenerator->generate($daemon);

        if (! $ssh->upload($config, $configPath)) {
            $daemon->delete();

            throw new \RuntimeException('Failed to upload supervisor configuration.');
        }

        $ssh->run('supervisorctl reread && supervisorctl update')->throw();
        $statusResult = $ssh->run('supervisorctl start '.$dto->name.':*');
        $daemon->forceFill([
            'status' => $this->parseStatus($statusResult),
        ])->save();

        AuditLog::record(
            operation: 'daemon.created',
            resource: $daemon,
            afterState: [
                'server_id' => (string) $server->getKey(),
                'name' => $dto->name,
            ],
        );

        return $daemon->refresh();
    }

    public function restart(SupervisorProcess $daemon, SSHConnectionInterface $ssh): SupervisorProcess
    {
        $result = $ssh->run('supervisorctl restart '.$daemon->name.':*');
        $daemon->forceFill(['status' => $this->parseStatus($result)])->save();

        AuditLog::record(
            operation: 'daemon.restarted',
            resource: $daemon,
            afterState: ['name' => $daemon->name],
        );

        return $daemon->refresh();
    }

    public function start(SupervisorProcess $daemon, SSHConnectionInterface $ssh): SupervisorProcess
    {
        $result = $ssh->run('supervisorctl start '.$daemon->name.':*');
        $daemon->forceFill(['status' => $this->parseStatus($result)])->save();

        AuditLog::record(
            operation: 'daemon.started',
            resource: $daemon,
            afterState: ['name' => $daemon->name],
        );

        return $daemon->refresh();
    }

    public function stop(SupervisorProcess $daemon, SSHConnectionInterface $ssh): SupervisorProcess
    {
        $result = $ssh->run('supervisorctl stop '.$daemon->name.':*');
        $daemon->forceFill(['status' => $this->parseStatus($result)])->save();

        AuditLog::record(
            operation: 'daemon.stopped',
            resource: $daemon,
            afterState: ['name' => $daemon->name],
        );

        return $daemon->refresh();
    }

    public function getLogs(SupervisorProcess $daemon, SSHConnectionInterface $ssh, int $lines = 50): string
    {
        return $ssh->run(sprintf(
            'tail -n %d %s',
            $lines,
            escapeshellarg('/var/log/supervisor/'.$daemon->name.'.log'),
        ))->output();
    }

    public function delete(SupervisorProcess $daemon, SSHConnectionInterface $ssh): void
    {
        $ssh->run('supervisorctl stop '.$daemon->name.':*');
        $configPath = $daemon->config_path ?? '/etc/supervisor/conf.d/'.$daemon->name.'.conf';
        $ssh->run('rm -f '.escapeshellarg($configPath));
        $ssh->run('supervisorctl reread && supervisorctl update')->throw();

        $server = $daemon->server;
        $beforeState = ['name' => $daemon->name, 'server_id' => $daemon->server_id];

        $daemon->delete();

        AuditLog::record(
            operation: 'daemon.deleted',
            resource: $server,
            beforeState: $beforeState,
        );
    }

    private function parseStatus(SSHResult $result): DaemonStatus
    {
        $output = $result->stdout.$result->stderr;

        if (str_contains($output, 'RUNNING')) {
            return DaemonStatus::RUNNING;
        }

        if (str_contains($output, 'FATAL') || str_contains($output, 'BACKOFF') || str_contains($output, 'ERROR')) {
            return DaemonStatus::ERROR;
        }

        return DaemonStatus::STOPPED;
    }
}
