<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions\EnvVarActions;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\DTOs\EnvVarsPullDiff;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\EnvFileManager;
use App\Modules\Sites\Services\EnvVarsPullComparer;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\SSHManager;

final class FetchEnvVarsPullPreviewAction
{
    public function __construct(
        private readonly EnvFileManager $envFileManager,
        private readonly EnvVarsPullComparer $envVarsPullComparer,
        private readonly SSHManager $sshManager,
        private readonly CredentialVault $credentialVault,
    ) {
    }

    public function execute(Site $site, Organization $org): EnvVarsPullDiff
    {
        $server = $site->server;
        abort_if($server === null, 404);

        return $this->withConnection($server, function (SSHConnectionInterface $connection) use ($site, $org): EnvVarsPullDiff {
            $content = $this->envFileManager->read($site, $connection);

            return $this->envVarsPullComparer->compare($site, $org, $content);
        });
    }

    private function withConnection(Server $server, callable $callback): EnvVarsPullDiff
    {
        $connection = $this->sshManager->connect($server, $this->credentialVault)->connect();

        try {
            return $callback($connection);
        } finally {
            $connection->disconnect();
        }
    }
}
