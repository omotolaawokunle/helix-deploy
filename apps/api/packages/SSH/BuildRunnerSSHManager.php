<?php

declare(strict_types=1);

namespace App\Packages\SSH;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\CredentialVault;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class BuildRunnerSSHManager
{
    public function connect(BuildRunner $runner, CredentialVault $vault): SSHConnectionInterface
    {
        $privateKey = $vault->getPrivateKey((string) $runner->credential_id, $runner->organization);

        return new BuildRunnerSSHConnection(
            runner: $runner,
            privateKeyContent: $privateKey,
        );
    }

    public function connectAndVerify(BuildRunner $runner, CredentialVault $vault): SSHConnectionInterface
    {
        $connection = $this->connect($runner, $vault)->connect();
        $connection->run('echo "_helix_ok_"')->throw();

        return $connection;
    }
}
