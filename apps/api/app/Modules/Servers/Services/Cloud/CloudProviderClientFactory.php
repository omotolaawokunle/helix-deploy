<?php

declare(strict_types=1);

namespace App\Modules\Servers\Services\Cloud;

use App\Modules\Servers\Contracts\CloudProviderClientInterface;
use App\Modules\Servers\Enums\CloudProvider;

final class CloudProviderClientFactory
{
    public function for(CloudProvider $provider): CloudProviderClientInterface
    {
        return match ($provider) {
            CloudProvider::HETZNER => new HetznerCloudProviderClient(),
            CloudProvider::DIGITALOCEAN => new DigitalOceanCloudProviderClient(),
            CloudProvider::AWS => new AwsEc2CloudProviderClient(),
        };
    }
}
