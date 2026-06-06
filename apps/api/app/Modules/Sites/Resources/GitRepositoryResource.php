<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use App\Modules\Sites\DTOs\GitRepositoryDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GitRepositoryDTO
 */
class GitRepositoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GitRepositoryDTO $repository */
        $repository = $this->resource;

        return $repository->toArray();
    }
}
