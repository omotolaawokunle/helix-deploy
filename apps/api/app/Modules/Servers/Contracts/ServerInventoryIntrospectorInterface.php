<?php

declare(strict_types=1);

namespace App\Modules\Servers\Contracts;

use App\Modules\Servers\DTOs\ServerInventorySnapshot;
use App\Packages\SSH\Contracts\SSHConnectionInterface;

interface ServerInventoryIntrospectorInterface
{
    public function inspect(SSHConnectionInterface $connection): ServerInventorySnapshot;
}
