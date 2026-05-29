<?php

declare(strict_types=1);

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'currentOrganizationId' => $this->current_organization_id,
            'timezone' => (string) $this->timezone,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
