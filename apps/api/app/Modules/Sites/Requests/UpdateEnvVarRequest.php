<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnvVarRequest extends FormRequest
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
            'value' => ['required', 'string', 'max:65535'],
        ];
    }
}
