<?php

declare(strict_types=1);

namespace App\Packages\Execution\Contracts;

use App\Packages\Execution\DeploymentContext;

interface DeploymentStepInterface
{
    public function name(): string;

    public function run(DeploymentContext $ctx): void;

    public function rollback(DeploymentContext $ctx): void;

    public function isSkippable(DeploymentContext $ctx): bool;
}
