<?php

declare(strict_types=1);

namespace App\Packages\SSH;

use App\Packages\SSH\Exceptions\SSHCommandFailedException;

readonly class SSHResult
{
    public function __construct(
        public string $command,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public float $duration,
    ) {
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function output(): string
    {
        return trim($this->stdout) ?: trim($this->stderr);
    }

    public function throw(): static
    {
        if ($this->failed()) {
            throw new SSHCommandFailedException($this);
        }

        return $this;
    }
}
