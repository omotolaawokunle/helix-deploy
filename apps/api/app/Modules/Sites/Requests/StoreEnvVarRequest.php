<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnvVarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Z][A-Z0-9_]*$/',
            ],
            'value' => ['required', 'string', 'max:65535'],
        ];
    }
}
