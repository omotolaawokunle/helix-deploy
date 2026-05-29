<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
