<?php

declare(strict_types=1);

namespace App\Modules\Servers\Requests;

use App\Modules\Servers\Enums\ManagementMode;
use App\Modules\Servers\Enums\ServerProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterServerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255'],
            'ipAddress' => ['required', 'ip'],
            'sshPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'sshUser' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(array_column(ServerProvider::cases(), 'value'))],
            'region' => ['nullable', 'string', 'max:255'],
            'serverType' => ['nullable', 'string', 'max:255'],
            'providerInstanceId' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:255'],
            'managementMode' => ['required', 'string', Rule::in(array_column(ManagementMode::cases(), 'value'))],
            'authMethod' => ['required', 'string', Rule::in(['generate', 'import'])],
            'privateKey' => ['nullable', 'string', 'required_if:authMethod,import'],
            'projectId' => ['nullable', 'uuid', 'exists:projects,id'],
            'environmentId' => ['nullable', 'uuid', 'exists:environments,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
