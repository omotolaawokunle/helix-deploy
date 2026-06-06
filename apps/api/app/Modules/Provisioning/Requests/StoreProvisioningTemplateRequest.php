<?php

declare(strict_types=1);

namespace App\Modules\Provisioning\Requests;

use App\Packages\Provisioning\Enums\NodejsVersion;
use App\Packages\Provisioning\Enums\PhpVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProvisioningTemplateRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => [
                'required',
                'string',
                Rule::in($this->allowedServices()),
            ],
            'options' => ['nullable', 'array'],
            'options.phpVersion' => ['nullable', 'string', Rule::in(PhpVersion::values())],
            'options.nodeVersion' => ['nullable', 'integer', Rule::in(NodejsVersion::values())],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedServices(): array
    {
        return [
            'create-deploy-user',
            'nginx',
            'php',
            'mysql',
            'postgresql',
            'redis',
            'nodejs',
            'python',
            'supervisor',
            'docker',
            'certbot',
        ];
    }
}
