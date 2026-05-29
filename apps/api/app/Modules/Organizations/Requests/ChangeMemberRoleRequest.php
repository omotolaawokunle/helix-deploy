<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Requests;

use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeMemberRoleRequest extends FormRequest
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
        return [
            'role' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (TeamRole $role): string => $role->value,
                    TeamRole::cases(),
                )),
            ],
        ];
    }
}
