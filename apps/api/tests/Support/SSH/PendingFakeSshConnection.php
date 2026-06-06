<?php

declare(strict_types=1);

namespace Tests\Support\SSH;

use App\Packages\SSH\Contracts\SSHConnectionInterface;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;

final class PendingFakeSshConnection implements SSHConnectionInterface
{
    public function __construct(private readonly FakeSSHConnection $fake)
    {
    }

    public function connect(): static
    {
        $this->fake->connect();

        return $this;
    }

    public function run(string $command, ?callable $lineCallback = null, ?int $timeout = null): SSHResult
    {
        return $this->fake->run($command, $lineCallback, $timeout);
    }

    public function upload(string $content, string $remotePath): bool
    {
        return $this->fake->upload($content, $remotePath);
    }

    public function interrupt(): void
    {
        $this->fake->interrupt();
    }

    public function disconnect(): void
    {
        $this->fake->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->fake->isConnected();
    }
}
