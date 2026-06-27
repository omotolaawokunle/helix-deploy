<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteDatabaseBrowseReady implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $siteId,
        public readonly string $kind,
        public readonly ?string $table,
        public readonly int $limit,
        public readonly ?int $page = null,
        /** @var list<array{column: string, operator: string, value: string|null}>|null */
        public readonly ?array $filters = null,
        public readonly string $status = 'ready',
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->serverId.'.databases'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'site.database.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
            'siteId' => $this->siteId,
            'kind' => $this->kind,
            'table' => $this->table,
            'limit' => $this->limit,
            'page' => $this->page,
            'filters' => $this->filters ?? [],
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
