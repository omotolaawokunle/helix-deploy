<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Actions;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Daemons\Models\SupervisorProcess;
use App\Modules\Daemons\Services\SupervisorService;
use App\Packages\SSH\SSHManager;

class StopDaemonAction
{
    public function __construct(
        private readonly SupervisorService $supervisorService,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function execute(string $daemonId): SupervisorProcess
    {
        $daemon = SupervisorProcess::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($daemonId)
            ->firstOrFail();

        $server = $daemon->server;
        abort_if($server === null, 404);

        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            return $this->supervisorService->stop($daemon, $connection);
        } finally {
            $connection->disconnect();
        }
    }
}
