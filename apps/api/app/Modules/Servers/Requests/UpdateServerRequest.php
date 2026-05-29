<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServerRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'projectId' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
            'environmentId' => ['sometimes', 'nullable', 'uuid', 'exists:environments,id'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
