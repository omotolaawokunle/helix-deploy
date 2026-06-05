<?php

declare(strict_types=1);

namespace App\Modules\Commands\Policies;

use App\Models\User;
use App\Modules\Commands\Models\Command;
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

    public function view(User $user, Command $command): bool
    {
        $server = $command->server;

        return $server !== null && $this->serverPolicy->runCommands($user, $server);
    }

    public function cancel(User $user, Command $command): bool
    {
        return $this->view($user, $command);
    }
}
