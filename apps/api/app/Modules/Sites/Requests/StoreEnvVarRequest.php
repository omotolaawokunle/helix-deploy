<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'value' => ['required_without:referencedCredentialId', 'nullable', 'string', 'max:65535'],
            'referencedCredentialId' => ['required_without:value', 'nullable', 'uuid', 'prohibited_unless:value,null'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('value');
            $reference = $this->input('referencedCredentialId');

            $hasValue = is_string($value) && $value !== '';
            $hasReference = is_string($reference) && $reference !== '';

            if ($hasValue && $hasReference) {
                $validator->errors()->add('value', 'Provide either a value or a server credential reference, not both.');
            }

            if (! $hasValue && ! $hasReference) {
                $validator->errors()->add('value', 'A value or server credential reference is required.');
            }
        });
    }
}
