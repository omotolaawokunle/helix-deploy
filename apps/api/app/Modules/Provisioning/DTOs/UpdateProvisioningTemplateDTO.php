<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\DTOs;

use App\Modules\Provisioning\Requests\UpdateProvisioningTemplateRequest;

final readonly class UpdateProvisioningTemplateDTO
{
    /**
     * @param list<string>|null $services
     * @param array<string, mixed>|null $options
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?array $services,
        public ?array $options,
    ) {
    }

    public static function fromRequest(UpdateProvisioningTemplateRequest $request): self
    {
        $services = null;

        if ($request->has('services')) {
            /** @var list<string> $services */
            $services = array_values(array_map(
                static fn (mixed $service): string => (string) $service,
                $request->input('services', []),
            ));
        }

        $options = $request->has('options')
            ? (array) $request->input('options', [])
            : null;

        return new self(
            name: $request->has('name') ? (string) $request->input('name') : null,
            description: $request->has('description')
                ? ($request->input('description') !== null ? (string) $request->input('description') : null)
                : null,
            services: $services,
            options: $options,
        );
    }
}
