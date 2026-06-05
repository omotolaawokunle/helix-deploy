<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\DTOs;

use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Teams\Enums\TeamRole;

final readonly class PipelineStepInputDTO
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public ?string $id,
        public string $name,
        public PipelineStepType $type,
        public int $order,
        public array $config,
        public bool $requiresApproval,
        public ?TeamRole $approverRole,
        public int $retryAttempts,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $approverRole = $payload['approverRole'] ?? null;

        return new self(
            id: isset($payload['id']) ? (string) $payload['id'] : null,
            name: (string) ($payload['name'] ?? ''),
            type: PipelineStepType::from((string) ($payload['type'] ?? PipelineStepType::DEPLOY->value)),
            order: (int) ($payload['order'] ?? 0),
            config: is_array($payload['config'] ?? null) ? $payload['config'] : [],
            requiresApproval: (bool) ($payload['requiresApproval'] ?? false),
            approverRole: is_string($approverRole) && $approverRole !== ''
                ? TeamRole::from($approverRole)
                : null,
            retryAttempts: (int) ($payload['retryAttempts'] ?? 0),
        );
    }
}
