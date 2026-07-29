<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

readonly class LaravelWorkerPreset
{
    public function __construct(
        public string $daemonName,
        public string $daemonCommand,
        public string $daemonDirectory,
        public string $daemonUser,
        public int $daemonProcesses,
        public string $cronExpression,
        public string $cronCommand,
        public string $cronUser,
    ) {
    }
}
