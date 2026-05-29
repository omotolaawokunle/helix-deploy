<?php

declare(strict_types=1);

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;

class UserWithOrgResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $organization = $this->currentOrganizationRelation;

        $data['currentOrganization'] = $organization === null
            ? null
            : OrganizationResource::make($organization);

        return $data;
    }
}
