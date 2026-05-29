<?php

declare(strict_types=1);

namespace App\Packages\SSH\Exceptions;

use App\Packages\SSH\SSHResult;

class SSHCommandFailedException extends SSHConnectionException
{
    public function __construct(public readonly SSHResult $result)
    {
        parent::__construct(sprintf(
            'SSH command failed [%s] with exit code %d: %s',
            $result->command,
            $result->exitCode,
            $result->output(),
        ));
    }
}
