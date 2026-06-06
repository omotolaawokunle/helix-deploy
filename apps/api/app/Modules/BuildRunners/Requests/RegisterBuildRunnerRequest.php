<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Requests;

use App\Modules\Sites\Enums\Runtime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterBuildRunnerRequest extends FormRequest
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
            'ipAddress' => ['required', 'ip'],
            'sshPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'sshUser' => ['required', 'string', 'max:255'],
            'authMethod' => ['required', 'string', Rule::in(['generate', 'import'])],
            'privateKey' => ['nullable', 'string', 'required_if:authMethod,import'],
            'maxConcurrentBuilds' => ['required', 'integer', 'min:1', 'max:32'],
            'cpuCores' => ['nullable', 'integer', 'min:1', 'max:256'],
            'ramGb' => ['nullable', 'integer', 'min:1', 'max:2048'],
            'supportedRuntimes' => ['required', 'array', 'min:1'],
            'supportedRuntimes.*' => ['required', 'string', Rule::in(array_column(Runtime::cases(), 'value'))],
            'projectId' => ['nullable', 'uuid', 'exists:projects,id'],
        ];
    }
}
