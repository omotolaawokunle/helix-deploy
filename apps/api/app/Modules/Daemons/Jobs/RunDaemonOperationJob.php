<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Jobs;

use App\Modules\Daemons\Actions\CreateDaemonAction;
use App\Modules\Daemons\Actions\DeleteDaemonAction;
use App\Modules\Daemons\Actions\RestartDaemonAction;
use App\Modules\Daemons\Actions\StartDaemonAction;
use App\Modules\Daemons\Actions\StopDaemonAction;
use App\Modules\Daemons\DTOs\CreateDaemonDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDaemonOperationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public function __construct(
        public readonly string $operation,
        public readonly ?string $daemonId = null,
        public readonly ?string $serverId = null,
        public readonly ?string $actorId = null,
        public readonly ?CreateDaemonDTO $dto = null,
    ) {
        $this->onQueue('commands');
    }

    public function handle(
        CreateDaemonAction $createDaemonAction,
        RestartDaemonAction $restartDaemonAction,
        StartDaemonAction $startDaemonAction,
        StopDaemonAction $stopDaemonAction,
        DeleteDaemonAction $deleteDaemonAction,
    ): void {
        match ($this->operation) {
            'create' => $createDaemonAction->execute(
                serverId: (string) $this->serverId,
                actorId: (string) $this->actorId,
                dto: $this->dto,
            ),
            'restart' => $restartDaemonAction->execute((string) $this->daemonId),
            'start' => $startDaemonAction->execute((string) $this->daemonId),
            'stop' => $stopDaemonAction->execute((string) $this->daemonId),
            'delete' => $deleteDaemonAction->execute((string) $this->daemonId),
            default => null,
        };
    }
}
