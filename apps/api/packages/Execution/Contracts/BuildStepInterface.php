<?php

declare(strict_types=1);

namespace App\Packages\Execution\Contracts;

use App\Packages\Execution\BuildContext;

interface BuildStepInterface
{
    public function name(): string;

    public function run(BuildContext $ctx): void;

    public function rollback(BuildContext $ctx): void;

    public function isSkippable(BuildContext $ctx): bool;
}
