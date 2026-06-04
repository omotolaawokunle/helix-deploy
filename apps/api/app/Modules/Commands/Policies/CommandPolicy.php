<?php

declare(strict_types=1);

namespace App\Modules\Commands\Policies;

use App\Models\User;
use App\Modules\Servers\Models\Server;
use App\Modules\Servers\Policies\ServerPolicy;

final class CommandPolicy
{
    public function __construct(
        private readonly ServerPolicy $serverPolicy,
    ) {}

    public function viewAny(User $user, Server $server): bool
    {
        return $this->serverPolicy->runCommands($user, $server);
    }

    public function create(User $user, Server $server): bool
    {
        return $this->serverPolicy->runCommands($user, $server);
    }
}
