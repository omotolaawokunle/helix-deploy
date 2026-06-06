<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\DTOs;

use App\Modules\Provisioning\Requests\StoreProvisioningTemplateRequest;

final readonly class CreateProvisioningTemplateDTO
{
    /**
     * @param list<string> $services
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $services,
        public array $options,
    ) {
    }

    public static function fromRequest(StoreProvisioningTemplateRequest $request): self
    {
        /** @var list<string> $services */
        $services = array_values(array_map(
            static fn (mixed $service): string => (string) $service,
            $request->input('services', []),
        ));

        /** @var array<string, mixed> $options */
        $options = $request->input('options', []);

        return new self(
            name: (string) $request->input('name'),
            description: $request->input('description') !== null ? (string) $request->input('description') : null,
            services: $services,
            options: $options,
        );
    }
}
