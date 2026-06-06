<?php

declare(strict_types=1);

namespace App\Packages\SSH;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

class SSHManager
{
    public function connect(Server $server, CredentialVault $vault): SSHConnectionInterface
    {
        $privateKey = $vault->getPrivateKey((string) $server->credential_id, $server->organization);

        return new SSHConnection(
            server: $server,
            privateKeyContent: $privateKey,
        );
    }

    public function connectAndVerify(Server $server, CredentialVault $vault): SSHConnectionInterface
    {
        $connection = $this->connect($server, $vault)->connect();
        $connection->run('echo "_helix_ok_"')->throw();

        return $connection;
    }

    public static function fake(): FakeSSHConnection
    {
        return new FakeSSHConnection();
    }
}
