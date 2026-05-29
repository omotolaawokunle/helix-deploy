<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\Contracts;

use App\Modules\Servers\Models\Server;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface ProvisioningScriptInterface
{
    public function name(): string;

    public function description(): string;

    public function estimatedMinutes(): int;

    /**
     * @param array<string, mixed> $options
     */
    public function handle(SSHConnectionInterface $ssh, Server $server, array $options = []): void;

    public function isIdempotent(): bool;
}
