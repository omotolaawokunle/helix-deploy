<?php

declare(strict_types=1);

namespace App\Modules\Deployments\DTOs;

final readonly class TriggerDeploymentDTO
{
    public function __construct(
        public ?string $branch = null,
    ) {
    }
}
