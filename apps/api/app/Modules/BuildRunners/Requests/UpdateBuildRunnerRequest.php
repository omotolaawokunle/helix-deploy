<?php

declare(strict_types=1);

namespace App\Modules\BuildRunners\Requests;

use App\Modules\BuildRunners\Enums\BuildRunnerStatus;
use App\Modules\Sites\Enums\Runtime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildRunnerRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'maxConcurrentBuilds' => ['sometimes', 'integer', 'min:1', 'max:32'],
            'cpuCores' => ['nullable', 'integer', 'min:1', 'max:256'],
            'ramGb' => ['nullable', 'integer', 'min:1', 'max:2048'],
            'supportedRuntimes' => ['sometimes', 'array', 'min:1'],
            'supportedRuntimes.*' => ['required', 'string', Rule::in(array_column(Runtime::cases(), 'value'))],
            'status' => ['sometimes', 'string', Rule::in(array_column(BuildRunnerStatus::cases(), 'value'))],
            'projectId' => ['nullable', 'uuid', 'exists:projects,id'],
        ];
    }
}
