<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

use App\Modules\Servers\Requests\UpdateServerGroupRequest;

final readonly class UpdateServerGroupDTO
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public bool $hasDescription,
    ) {
    }

    public static function fromRequest(UpdateServerGroupRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: array_key_exists('name', $validated) ? (string) $validated['name'] : null,
            description: array_key_exists('description', $validated) ? $validated['description'] : null,
            hasDescription: array_key_exists('description', $validated),
        );
    }
}
