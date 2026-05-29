<?php

declare(strict_types=1);

namespace App\Modules\Organizations\DTOs;

use Illuminate\Http\Request;

readonly class CreateOrganizationDTO
{
    public function __construct(
        public string $name,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array{name?: string} $validated */
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->only(['name']);

        return new self(
            name: (string) ($validated['name'] ?? ''),
        );
    }
}
