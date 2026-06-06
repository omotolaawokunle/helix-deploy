<?php

declare(strict_types=1);

namespace App\Modules\Servers\Commands;

use App\Modules\Credentials\CredentialVault;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use App\Packages\SSH\SSHManager;
use Illuminate\Console\Command;
use Throwable;

final class PingServerCommand extends Command
{
    protected $signature = 'server:ping {server_id : Server UUID}';

    protected $description = 'Test SSH connectivity and report fingerprint status.';

    public function handle(SSHManager $sshManager, CredentialVault $credentialVault): int
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey((string) $this->argument('server_id'))
            ->first();

        if ($server === null) {
            $this->error('Server not found.');

            return self::FAILURE;
        }

        $this->line(sprintf('Server: %s (%s)', (string) $server->hostname, (string) $server->ip_address));
        $this->line(sprintf('Stored fingerprint: %s', $server->fingerprint ?? '(none — TOFU on first connect)'));

        try {
            $connection = $sshManager->connectAndVerify($server, $credentialVault);
            $result = $connection->run('echo "_ping_"', timeout: 10)->throw();
            $connection->disconnect();

            $server->refresh();

            $this->info('SSH connection: OK');
            $this->line('Probe output: '.trim($result->stdout));
            $this->line(sprintf('Fingerprint status: %s', $this->fingerprintStatus($server)));

            return self::SUCCESS;
        } catch (SSHFingerprintMismatchException $exception) {
            $this->error('SSH fingerprint mismatch — connection blocked.');
            $this->line(sprintf('Expected: %s', $exception->expectedFingerprint));
            $this->line(sprintf('Received: %s', $exception->receivedFingerprint));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('SSH connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function fingerprintStatus(Server $server): string
    {
        if ($server->fingerprint === null) {
            return 'stored on first successful connection (TOFU)';
        }

        return 'verified ('.$server->fingerprint.')';
    }
}
