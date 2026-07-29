<?php

declare(strict_types=1);

namespace App\Modules\Deployments\DTOs;

use App\Modules\Deployments\Enums\TriggerType;

final readonly class TriggerDeploymentDTO
{
    public function __construct(
        public ?string $branch = null,
        public TriggerType $triggerType = TriggerType::MANUAL,
        public ?string $commitHash = null,
        public ?string $commitMessage = null,
    ) {
    }
}
