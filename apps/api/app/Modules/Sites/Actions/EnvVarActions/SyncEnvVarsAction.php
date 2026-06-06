<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Models\Organization;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\EnvFileManager;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHManager;

class SyncEnvVarsAction
{
    public function __construct(
        private readonly EnvFileManager $envFileManager,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function execute(Site $site, Organization $org): void
    {
        $server = $site->server;
        abort_if($server === null, 404);

        $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site, $org): void {
            $this->envFileManager->sync($site, $org, $connection);
        });
    }

    private function withConnection(Server $server, callable $callback): void
    {
        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            $callback($connection);
        } finally {
            $connection->disconnect();
        }
    }
}
