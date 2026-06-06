<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use App\Modules\Servers\Enums\CloudProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCloudProviderCredentialRequest extends FormRequest
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
        $provider = CloudProvider::tryFrom((string) $this->input('provider'));

        return [
            'provider' => ['required', 'string', Rule::enum(CloudProvider::class)],
            'token' => [
                Rule::requiredIf($provider !== CloudProvider::AWS),
                'nullable',
                'string',
                'max:4096',
            ],
            'accessKeyId' => [
                Rule::requiredIf($provider === CloudProvider::AWS),
                'nullable',
                'string',
                'max:255',
            ],
            'secretAccessKey' => [
                Rule::requiredIf($provider === CloudProvider::AWS),
                'nullable',
                'string',
                'max:4096',
            ],
            'region' => [
                Rule::requiredIf($provider === CloudProvider::AWS),
                'nullable',
                'string',
                'max:64',
            ],
        ];
    }

    public function provider(): CloudProvider
    {
        return CloudProvider::from((string) $this->validated('provider'));
    }
}
