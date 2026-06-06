<?php

declare(strict_types=1);

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Enums\ApiTokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ability' => ['required', 'string', Rule::enum(ApiTokenAbility::class)],
        ];
    }
}
