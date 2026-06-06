<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Events;

use App\Modules\Integrations\Enums\DnsStatus;
use App\Modules\Sites\Enums\SslStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteDnsSslStatusChanged implements ShouldBroadcastNow
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
        return 'site.dns_ssl.status_changed';
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
            'dnsStatus' => $this->site->dns_status instanceof DnsStatus
                ? $this->site->dns_status->value
                : $this->site->dns_status,
            'dnsError' => $this->site->dns_error,
            'sslStatus' => $this->site->ssl_status instanceof SslStatus
                ? $this->site->ssl_status->value
                : $this->site->ssl_status,
            'sslError' => $this->site->ssl_error,
        ];
    }
}
