<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\DTOs;

use App\Modules\Pipelines\Requests\StorePipelineRequest;

final readonly class CreatePipelineDTO
{
    /**
     * @param list<PipelineStepInputDTO> $steps
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $projectId,
        public array $steps,
    ) {
    }

    public static function fromRequest(StorePipelineRequest $request): self
    {
        /** @var list<array<string, mixed>> $rawSteps */
        $rawSteps = $request->input('steps', []);

        $steps = array_map(
            static fn (array $step): PipelineStepInputDTO => PipelineStepInputDTO::fromArray($step),
            $rawSteps,
        );

        $projectId = $request->validated('projectId');

        return new self(
            name: (string) $request->validated('name'),
            description: $request->validated('description'),
            projectId: is_string($projectId) ? $projectId : null,
            steps: $steps,
        );
    }
}
