<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Actions;

use App\Models\User;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Services\SupervisorService;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\SSHManager;

class CreateDaemonAction
{
    public function __construct(
        private readonly SupervisorService $supervisorService,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function execute(string $serverId, string $actorId, CreateDaemonDTO $dto): SupervisorProcess
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($serverId)
            ->firstOrFail();

        $actor = User::query()->findOrFail($actorId);

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            return $this->supervisorService->create($server, $connection, $dto, $actor);
        } finally {
            $connection->disconnect();
        }
    }
}
