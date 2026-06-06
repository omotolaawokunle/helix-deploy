<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

final readonly class GitBranchDTO
{
    public function __construct(
        public string $name,
        public bool $isDefault,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'isDefault' => $this->isDefault,
        ];
    }
}
