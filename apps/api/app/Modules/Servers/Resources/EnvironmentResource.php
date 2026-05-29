<?php

declare(strict_types=1);

namespace App\Modules\Servers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Projects\Models\Environment
 */
class EnvironmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'name' => $this->name,
            'label' => $this->label,
            'isProduction' => (bool) $this->is_production,
        ];
    }
}
