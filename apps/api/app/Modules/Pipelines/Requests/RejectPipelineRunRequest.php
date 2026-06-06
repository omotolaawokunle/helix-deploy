<?php

declare(strict_types=1);

namespace App\Modules\Pipelines\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectPipelineRunRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
