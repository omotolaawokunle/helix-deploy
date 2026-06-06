<?php

declare(strict_types=1);

namespace App\Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnvironmentRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/',
                Rule::unique('environments', 'name')->where(
                    fn ($query) => $query->where('project_id', $this->route('project')),
                ),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'isProduction' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The environment name must use lowercase letters, numbers, hyphens, or underscores.',
        ];
    }
}
