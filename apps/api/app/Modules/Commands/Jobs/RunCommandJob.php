<?php

declare(strict_types=1);

namespace App\Modules\Commands\Jobs;

use App\Models\User;
use App\Modules\Commands\Services\CommandService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly string $serverId,
        public readonly string $command,
        public readonly string $userId,
        public readonly string $organizationId,
    ) {
        $this->onQueue('commands');
    }

    public function handle(CommandService $commandService): void
    {
        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        $actor = User::query()->whereKey($this->userId)->first();

        $organization = Organization::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->organizationId)
            ->first();

        if ($server === null || $actor === null || $organization === null) {
            return;
        }

        $commandService->run($server, $this->command, $actor, $organization);
    }
}
