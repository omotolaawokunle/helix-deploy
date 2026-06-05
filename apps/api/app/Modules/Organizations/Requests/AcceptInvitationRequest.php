<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
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
            'token' => ['required', 'string', 'min:32'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Invitation link is missing a token.',
            'expires.required' => 'Invitation link is missing an expiration.',
            'signature.required' => 'Invitation link is missing a signature.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'token' => $this->query('token'),
            'expires' => $this->query('expires'),
            'signature' => $this->query('signature'),
        ]);
    }
}
