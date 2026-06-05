<?php

declare(strict_types=1);

namespace App\Packages\SSH;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\Exceptions\SSHConnectionException;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use App\Packages\SSH\Exceptions\SSHTimeoutException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Throwable;

class SSHConnection implements SSHConnectionInterface
{
    private SSH2 $ssh;

    private bool $connected = false;

    public function __construct(
        private readonly Server $server,
        private readonly string $privateKeyContent,
        private readonly int $defaultTimeout = 30,
    ) {
    }

    public function connect(): static
    {
        $this->ssh = $this->createSSHClient($this->server->ip_address, (int) $this->server->ssh_port);

        try {
            $key = $this->loadPrivateKey($this->privateKeyContent);
        } catch (Throwable $exception) {
            throw new SSHConnectionException('Failed to load SSH private key.', previous: $exception);
        }

        if (! $this->ssh->login($this->server->ssh_user, $key)) {
            throw new SSHConnectionException(sprintf(
                'Unable to authenticate SSH session for server [%s] user [%s].',
                (string) $this->server->getKey(),
                (string) $this->server->ssh_user,
            ));
        }

        $this->verifyFingerprint();
        $this->connected = true;

        return $this;
    }

    public function run(string $command, ?callable $lineCallback = null, ?int $timeout = null): SSHResult
    {
        $this->ensureConnected();

        $effectiveTimeout = $timeout ?? $this->defaultTimeout;
        $this->ssh->setTimeout($effectiveTimeout);

        $buffer = '';
        $start = hrtime(true);

        $stdout = (string) $this->ssh->exec($command, function (string $chunk) use ($lineCallback, &$buffer): void {
            if ($lineCallback === null) {
                return;
            }

            $buffer .= $chunk;
            $parts = preg_split('/\r\n|\r|\n/', $buffer);

            if ($parts === false || $parts === []) {
                return;
            }

            $buffer = (string) array_pop($parts);

            foreach ($parts as $line) {
                if ($line === '') {
                    continue;
                }

                $lineCallback($line);
            }
        });

        if ($lineCallback !== null && trim($buffer) !== '') {
            $lineCallback(trim($buffer));
        }

        if (method_exists($this->ssh, 'isTimeout') && $this->ssh->isTimeout()) {
            throw new SSHTimeoutException(sprintf(
                'SSH command timed out after %d seconds: %s',
                $effectiveTimeout,
                $command,
            ));
        }

        $end = hrtime(true);
        $duration = ($end - $start) / 1_000_000_000;
        $exitCode = $this->ssh->getExitStatus();
        $stderr = $this->ssh->getStdError();

        return new SSHResult(
            command: $command,
            exitCode: $exitCode ?? 0,
            stdout: $stdout,
            stderr: $stderr,
            duration: $duration,
        );
    }

    public function upload(string $content, string $remotePath): bool
    {
        $this->ensureConnected();

        if (method_exists($this->ssh, 'put')) {
            /** @phpstan-ignore-next-line */
            return (bool) $this->ssh->put($remotePath, $content);
        }

        $encoded = base64_encode($content);
        $escapedPath = escapeshellarg($remotePath);
        $escapedEncoded = escapeshellarg($encoded);
        $command = "echo {$escapedEncoded} | base64 --decode > {$escapedPath}";

        return $this->run($command)->successful();
    }

    public function interrupt(): void
    {
        if (! $this->connected || ! isset($this->ssh)) {
            return;
        }

        if (method_exists($this->ssh, 'write')) {
            $this->ssh->write("\x03");
        }

        $this->disconnect();
    }

    public function disconnect(): void
    {
        if (! isset($this->ssh)) {
            $this->connected = false;

            return;
        }

        if (method_exists($this->ssh, 'disconnect')) {
            $this->ssh->disconnect();
        }

        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    protected function createSSHClient(string $host, int $port): SSH2
    {
        return new SSH2($host, $port);
    }

    protected function loadPrivateKey(string $privateKeyContent): PrivateKey
    {
        return PublicKeyLoader::load($privateKeyContent);
    }

    private function verifyFingerprint(): void
    {
        $hostKey = $this->ssh->getServerPublicHostKey();

        if ($hostKey === false || $hostKey === '') {
            throw new SSHConnectionException('Unable to retrieve server host public key for fingerprint verification.');
        }

        $receivedFingerprint = hash('sha256', $hostKey);
        $storedFingerprint = $this->server->fingerprint;

        if ($storedFingerprint === null) {
            $this->server->update(['fingerprint' => $receivedFingerprint]);
            $this->writeFingerprintStoredAudit($receivedFingerprint);

            return;
        }

        if (! hash_equals((string) $storedFingerprint, $receivedFingerprint)) {
            throw new SSHFingerprintMismatchException(
                server: $this->server,
                expectedFingerprint: (string) $storedFingerprint,
                receivedFingerprint: $receivedFingerprint,
            );
        }
    }

    private function writeFingerprintStoredAudit(string $fingerprint): void
    {
        AuditLog::query()->create([
            'organization_id' => (string) $this->server->organization_id,
            'actor_id' => null,
            'operation' => 'server.fingerprint_stored',
            'resource_type' => Server::class,
            'resource_id' => (string) $this->server->getKey(),
            'before_state' => ['fingerprint' => null],
            'after_state' => ['fingerprint' => $fingerprint],
            'ip_address' => null,
            'user_agent' => null,
            'request_id' => null,
            'created_at' => now(),
        ]);
    }

    private function ensureConnected(): void
    {
        if (! $this->connected) {
            throw new SSHConnectionException('SSH connection is not established.');
        }
    }
}
