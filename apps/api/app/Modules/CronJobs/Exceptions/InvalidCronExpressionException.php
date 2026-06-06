<?php

declare(strict_types=1);

namespace App\Modules\CronJobs\Exceptions;

use Exception;

class InvalidCronExpressionException extends Exception
{
    public function __construct(string $expression)
    {
        parent::__construct("The cron expression \"{$expression}\" is not valid.");
    }
}
