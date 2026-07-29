<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

use App\Modules\Sites\Models\Site;

final readonly class UpdateSiteAutoDeployResult
{
    public function __construct(
        public Site $site,
        public ?string $revealedWebhookSecret = null,
    ) {
    }
}
