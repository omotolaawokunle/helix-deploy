<?php

declare(strict_types=1);

namespace App\Packages\Provisioning\DTOs;

readonly class ServiceVersionDefinition
{
    /**
     * @param list<string|int> $values
     */
    public function __construct(
        public string $serviceKey,
        public string $optionKey,
        public string $label,
        public array $values,
        public string|int $default,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'serviceKey' => $this->serviceKey,
            'optionKey' => $this->optionKey,
            'label' => $this->label,
            'values' => $this->values,
            'default' => $this->default,
        ];
    }
}
