<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

use App\Modules\Servers\Requests\StoreServerGroupRequest;

final readonly class CreateServerGroupDTO
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {
    }

    public static function fromRequest(StoreServerGroupRequest $request): self
    {
        return new self(
            name: (string) $request->validated('name'),
            description: $request->validated('description'),
        );
    }
}
