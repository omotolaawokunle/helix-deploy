<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Requests;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        $assignableRoles = array_values(array_filter(
            array_map(
                static fn (TeamRole $role): string => $role->value,
                TeamRole::cases(),
            ),
            static fn (string $role): bool => $role !== TeamRole::OWNER->value,
        ));

        /** @var Organization $organization */
        $organization = $this->route('org');

        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                'string',
                Rule::in($assignableRoles),
            ],
            'teamId' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('teams', 'id')->where(
                    static fn ($query) => $query->where('organization_id', (string) $organization->getKey()),
                ),
            ],
        ];
    }
}
