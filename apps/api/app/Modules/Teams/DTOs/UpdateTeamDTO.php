<?php

declare(strict_types=1);

namespace App\Modules\Teams\DTOs;

use App\Modules\Teams\Requests\UpdateTeamRequest;

readonly class UpdateTeamDTO
{
    public function __construct(
        public string $name,
    ) {
    }

    public static function fromRequest(UpdateTeamRequest $request): self
    {
        return new self(
            name: (string) $request->input('name'),
        );
    }
}
