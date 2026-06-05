<?php

declare(strict_types=1);

namespace App\Modules\Teams\Requests;

use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddTeamMemberRequest extends FormRequest
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

        return [
            'userId' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', 'string', Rule::in($assignableRoles)],
        ];
    }
}
