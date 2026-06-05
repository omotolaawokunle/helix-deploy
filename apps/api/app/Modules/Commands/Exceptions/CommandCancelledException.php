<?php

declare(strict_types=1);

namespace App\Modules\Commands\Exceptions;

use RuntimeException;

final class CommandCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Command was cancelled.');
    }
}
