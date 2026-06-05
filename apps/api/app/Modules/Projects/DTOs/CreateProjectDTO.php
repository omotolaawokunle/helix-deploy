<?php

declare(strict_types=1);

namespace App\Modules\Projects\DTOs;

use App\Modules\Projects\Requests\StoreProjectRequest;

final readonly class CreateProjectDTO
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {
    }

    public static function fromRequest(StoreProjectRequest $request): self
    {
        return new self(
            name: (string) $request->validated('name'),
            description: $request->validated('description'),
        );
    }
}
