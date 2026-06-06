<?php

declare(strict_types=1);

namespace App\Modules\Sites\DTOs;

final readonly class GitRepositoryDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $fullName,
        public string $cloneUrl,
        public string $defaultBranch,
        public bool $isPrivate,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fullName' => $this->fullName,
            'cloneUrl' => $this->cloneUrl,
            'defaultBranch' => $this->defaultBranch,
            'isPrivate' => $this->isPrivate,
        ];
    }
}
