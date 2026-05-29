<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\DTOs;

use Illuminate\Http\Request;

readonly class ProvisionServerDTO
{
    /**
     * @param array<int, string> $scripts
     * @param array<string, mixed> $options
     */
    public function __construct(
        public array $scripts,
        public array $options,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array{scripts?: array<int, string>, options?: array<string, mixed>} $validated */
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        return new self(
            scripts: array_values($validated['scripts'] ?? []),
            options: $validated['options'] ?? [],
        );
    }
}
