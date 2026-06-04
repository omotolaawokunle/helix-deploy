<?php

declare(strict_types=1);

namespace App\Modules\Sites\Exceptions;

use RuntimeException;

class NginxConfigInvalidException extends RuntimeException
{
    public function __construct(
        public readonly string $domain,
        public readonly string $nginxTestOutput,
    ) {
        parent::__construct(sprintf('Nginx configuration test failed for site [%s].', $domain));
    }
}
