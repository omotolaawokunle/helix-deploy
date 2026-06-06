<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Commands;

use App\Modules\BuildRunners\Models\BuildRunner;
use App\Modules\Credentials\CredentialVault;
use App\Packages\SSH\BuildRunnerSSHManager;
use App\Packages\SSH\Exceptions\BuildRunnerSSHFingerprintMismatchException;
use Illuminate\Console\Command;
use Throwable;

final class PingBuildRunnerCommand extends Command
{
    protected $signature = 'runners:ping {runner_id : Build runner UUID}';

    protected $description = 'Test SSH connectivity to a build runner and report fingerprint status.';

    public function handle(BuildRunnerSSHManager $sshManager, CredentialVault $credentialVault): int
    {
        $runner = BuildRunner::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey((string) $this->argument('runner_id'))
            ->first();

        if ($runner === null) {
            $this->error('Build runner not found.');

            return self::FAILURE;
        }

        $this->line(sprintf('Runner: %s (%s@%s)', (string) $runner->name, (string) $runner->ssh_user, (string) $runner->ip_address));
        $this->line(sprintf('Stored fingerprint: %s', $runner->fingerprint ?? '(none — TOFU on first connect)'));

        try {
            $connection = $sshManager->connectAndVerify($runner, $credentialVault);
            $result = $connection->run('echo "_ping_"', timeout: 10)->throw();
            $connection->disconnect();

            $runner->refresh();

            $this->info('SSH connection: OK');
            $this->line('Probe output: '.trim($result->stdout));
            $this->line(sprintf('Fingerprint status: %s', $this->fingerprintStatus($runner)));

            return self::SUCCESS;
        } catch (BuildRunnerSSHFingerprintMismatchException $exception) {
            $this->error('SSH fingerprint mismatch — connection blocked.');
            $this->line(sprintf('Expected: %s', $exception->expectedFingerprint));
            $this->line(sprintf('Received: %s', $exception->receivedFingerprint));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('SSH connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function fingerprintStatus(BuildRunner $runner): string
    {
        if ($runner->fingerprint === null) {
            return 'stored on first successful connection (TOFU)';
        }

        return 'verified ('.$runner->fingerprint.')';
    }
}
