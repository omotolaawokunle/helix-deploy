<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

use App\Modules\Servers\Enums\ServiceRuntimeStatus;

readonly class InstalledServiceDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $installed,
        public ServiceRuntimeStatus $status,
        public ?string $statusCheckedAt,
        public bool $controllable,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'installed' => $this->installed,
            'status' => $this->status->value,
            'statusCheckedAt' => $this->statusCheckedAt,
            'controllable' => $this->controllable,
        ];
    }
}
