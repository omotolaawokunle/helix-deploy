<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\DTOs;

use App\Modules\Pipelines\Requests\UpdatePipelineRequest;

final readonly class UpdatePipelineDTO
{
    /**
     * @param list<PipelineStepInputDTO>|null $steps
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public bool $hasDescription,
        public ?string $projectId,
        public bool $hasProjectId,
        public ?array $steps,
    ) {
    }

    public static function fromRequest(UpdatePipelineRequest $request): self
    {
        $validated = $request->validated();

        $steps = null;

        if (array_key_exists('steps', $validated)) {
            /** @var list<array<string, mixed>> $rawSteps */
            $rawSteps = is_array($validated['steps']) ? $validated['steps'] : [];

            $steps = array_map(
                static fn (array $step): PipelineStepInputDTO => PipelineStepInputDTO::fromArray($step),
                $rawSteps,
            );
        }

        return new self(
            name: array_key_exists('name', $validated) ? (string) $validated['name'] : null,
            description: array_key_exists('description', $validated) ? $validated['description'] : null,
            hasDescription: array_key_exists('description', $validated),
            projectId: array_key_exists('projectId', $validated) && is_string($validated['projectId'])
                ? $validated['projectId']
                : null,
            hasProjectId: array_key_exists('projectId', $validated),
            steps: $steps,
        );
    }
}
