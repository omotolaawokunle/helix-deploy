<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Requests;

use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePipelineRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'projectId' => ['sometimes', 'nullable', 'uuid'],
            'steps' => ['sometimes', 'array'],
            'steps.*.id' => ['sometimes', 'uuid'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.type' => ['required_with:steps', Rule::enum(PipelineStepType::class)],
            'steps.*.order' => ['required_with:steps', 'integer', 'min:0'],
            'steps.*.config' => ['sometimes', 'array'],
            'steps.*.requiresApproval' => ['sometimes', 'boolean'],
            'steps.*.approverRole' => ['nullable', Rule::enum(TeamRole::class)],
            'steps.*.retryAttempts' => ['sometimes', 'integer', 'min:0', 'max:10'],
        ];
    }
}
