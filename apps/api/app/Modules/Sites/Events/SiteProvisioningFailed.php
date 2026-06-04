<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SiteProvisioningFailed implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $siteId,
        public readonly string $serverId,
        public readonly string $organizationId,
        public readonly string $domain,
        public readonly string $message,
        public readonly bool $siteRemoved = false,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->serverId.'.sites'),
            new PrivateChannel('organizations.'.$this->organizationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'site.provisioning.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'siteId' => $this->siteId,
            'serverId' => $this->serverId,
            'organizationId' => $this->organizationId,
            'domain' => $this->domain,
            'message' => $this->message,
            'siteRemoved' => $this->siteRemoved,
        ];
    }
}
