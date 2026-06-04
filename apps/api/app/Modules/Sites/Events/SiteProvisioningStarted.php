<?php

declare(strict_types=1);

namespace App\Modules\Sites\Events;

use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteProvisioningStarted implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Site $site)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('server.'.$this->site->server_id.'.sites'),
            new PrivateChannel('organizations.'.$this->site->organization_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'site.provisioning.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'siteId' => (string) $this->site->getKey(),
            'serverId' => $this->site->server_id,
            'organizationId' => $this->site->organization_id,
            'domain' => $this->site->domain,
            'status' => $this->site->status instanceof SiteStatus
                ? $this->site->status->value
                : $this->site->status,
            'runtime' => $this->site->runtime->value,
        ];
    }
}
