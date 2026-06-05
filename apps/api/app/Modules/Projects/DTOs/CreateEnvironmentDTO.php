<?php

declare(strict_types=1);

namespace App\Modules\Projects\DTOs;

use App\Modules\Projects\Requests\StoreEnvironmentRequest;

final readonly class CreateEnvironmentDTO
{
    public function __construct(
        public string $name,
        public ?string $label,
        public bool $isProduction,
    ) {
    }

    public static function fromRequest(StoreEnvironmentRequest $request): self
    {
        return new self(
            name: (string) $request->validated('name'),
            label: $request->validated('label'),
            isProduction: (bool) $request->validated('isProduction'),
        );
    }
}
