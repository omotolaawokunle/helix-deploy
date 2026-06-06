<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Events;

use App\Modules\Daemons\Models\SupervisorProcess;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DaemonChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>|null  $daemonSnapshot
     */
    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $daemonId,
        public readonly string $action,
        public readonly ?array $daemonSnapshot = null,
    ) {
    }

    public static function created(SupervisorProcess $daemon): self
    {
        return new self(
            serverId: (string) $daemon->server_id,
            organizationId: (string) $daemon->organization_id,
            daemonId: (string) $daemon->getKey(),
            action: 'created',
            daemonSnapshot: self::snapshot($daemon),
        );
    }

    public static function updated(SupervisorProcess $daemon): self
    {
        return new self(
            serverId: (string) $daemon->server_id,
            organizationId: (string) $daemon->organization_id,
            daemonId: (string) $daemon->getKey(),
            action: 'updated',
            daemonSnapshot: self::snapshot($daemon),
        );
    }

    public static function deleted(SupervisorProcess $daemon): self
    {
        return new self(
            serverId: (string) $daemon->server_id,
            organizationId: (string) $daemon->organization_id,
            daemonId: (string) $daemon->getKey(),
            action: 'deleted',
            daemonSnapshot: null,
        );
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->serverId.'.daemons'),
            new PrivateChannel('organizations.'.$this->organizationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'daemon.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
            'daemonId' => $this->daemonId,
            'action' => $this->action,
            'daemon' => $this->daemonSnapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(SupervisorProcess $daemon): array
    {
        return [
            'id' => (string) $daemon->getKey(),
            'serverId' => (string) $daemon->server_id,
            'organizationId' => (string) $daemon->organization_id,
            'name' => $daemon->name,
            'command' => $daemon->command,
            'directory' => $daemon->directory,
            'user' => $daemon->user,
            'processes' => $daemon->processes,
            'status' => $daemon->status->value,
            'createdAt' => $daemon->created_at?->toIso8601String(),
            'updatedAt' => $daemon->updated_at?->toIso8601String(),
        ];
    }
}
