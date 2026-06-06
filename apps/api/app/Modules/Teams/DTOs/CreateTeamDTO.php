<?php

declare(strict_types=1);

namespace App\Modules\Teams\DTOs;

use App\Modules\Teams\Requests\StoreTeamRequest;

readonly class CreateTeamDTO
{
    public function __construct(
        public string $name,
    ) {
    }

    public static function fromRequest(StoreTeamRequest $request): self
    {
        return new self(
            name: (string) $request->input('name'),
        );
    }
}
