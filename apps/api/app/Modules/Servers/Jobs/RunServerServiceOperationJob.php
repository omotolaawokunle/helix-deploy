<?php

declare(strict_types=1);

namespace App\Modules\Servers\Jobs;

use App\Modules\Servers\Actions\ListServerServicesAction;
use App\Modules\Servers\Actions\RestartServerServiceAction;
use App\Modules\Servers\Actions\StartServerServiceAction;
use App\Modules\Servers\Actions\StopServerServiceAction;
use App\Modules\Servers\Actions\SyncServerServiceStatusesAction;
use App\Modules\Servers\Events\ServerServiceStatusUpdated;
use App\Modules\Servers\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunServerServiceOperationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public function __construct(
        public readonly string $operation,
        public readonly string $serverId,
        public readonly ?string $serviceKey = null,
    ) {
        $this->onQueue('commands');
    }

    public function handle(
        ListServerServicesAction $listServerServicesAction,
        SyncServerServiceStatusesAction $syncServerServiceStatusesAction,
        StartServerServiceAction $startServerServiceAction,
        StopServerServiceAction $stopServerServiceAction,
        RestartServerServiceAction $restartServerServiceAction,
    ): void {
        match ($this->operation) {
            'sync-status' => $syncServerServiceStatusesAction->execute($this->serverId),
            'start' => $startServerServiceAction->execute($this->serverId, (string) $this->serviceKey),
            'stop' => $stopServerServiceAction->execute($this->serverId, (string) $this->serviceKey),
            'restart' => $restartServerServiceAction->execute($this->serverId, (string) $this->serviceKey),
            default => null,
        };

        $server = Server::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($this->serverId)
            ->first();

        if ($server === null) {
            return;
        }

        $services = array_map(
            static fn ($service): array => $service->toArray(),
            $listServerServicesAction->execute($server),
        );

        event(new ServerServiceStatusUpdated($server->refresh(), $services));
    }
}
