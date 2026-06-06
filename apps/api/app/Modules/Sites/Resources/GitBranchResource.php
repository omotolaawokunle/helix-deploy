<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use App\Modules\Sites\DTOs\GitBranchDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GitBranchDTO
 */
class GitBranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GitBranchDTO $branch */
        $branch = $this->resource;

        return $branch->toArray();
    }
}
