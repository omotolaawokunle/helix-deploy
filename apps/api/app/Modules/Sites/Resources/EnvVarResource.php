<?php

declare(strict_types=1);

namespace App\Modules\Sites\Resources;

use App\Modules\Credentials\Models\Credential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Credential
 */
class EnvVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'key' => $this->name,
            'maskedValue' => '••••••••',
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
