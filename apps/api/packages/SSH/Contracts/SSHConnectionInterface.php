<?php

declare(strict_types=1);

namespace App\Packages\SSH\Contracts;

use App\Packages\SSH\SSHResult;

interface SSHConnectionInterface
{
    public function connect(): static;

    /**
     * @param callable(string): void|null $lineCallback
     */
    public function run(string $command, ?callable $lineCallback = null, ?int $timeout = null): SSHResult;

    public function upload(string $content, string $remotePath): bool;

    public function interrupt(): void;

    public function disconnect(): void;

    public function isConnected(): bool;
}
