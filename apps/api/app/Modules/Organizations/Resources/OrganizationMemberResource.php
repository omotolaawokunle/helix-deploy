<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin \App\Models\User
 */
class OrganizationMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $joinedAt = $this->membership_joined_at ?? $this->pivot?->created_at;

        $joinedAtIso = match (true) {
            $joinedAt instanceof Carbon => $joinedAt->toISOString(),
            is_string($joinedAt) && $joinedAt !== '' => Carbon::parse($joinedAt)->toISOString(),
            default => null,
        };

        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'role' => (string) ($this->membership_role ?? $this->pivot?->role ?? ''),
            'joinedAt' => $joinedAtIso,
        ];
    }
}
