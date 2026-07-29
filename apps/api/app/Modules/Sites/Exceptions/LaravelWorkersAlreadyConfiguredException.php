<?php

declare(strict_types=1);

namespace App\Modules\Sites\Exceptions;

use RuntimeException;

class LaravelWorkersAlreadyConfiguredException extends RuntimeException
{
    public function __construct(
        public readonly string $siteId,
        public readonly string $reason,
    ) {
        parent::__construct($reason);
    }
}
