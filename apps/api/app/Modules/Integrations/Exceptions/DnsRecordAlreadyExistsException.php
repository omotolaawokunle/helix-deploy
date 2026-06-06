<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Exceptions;

use InvalidArgumentException;

final class DnsRecordAlreadyExistsException extends InvalidArgumentException
{
}
