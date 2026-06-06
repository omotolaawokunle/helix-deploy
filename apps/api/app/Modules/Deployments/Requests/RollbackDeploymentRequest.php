<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RollbackDeploymentRequest extends FormRequest
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
        $isProduction = false;
        $deploymentId = $this->route('deployment');

        if (is_string($deploymentId)) {
            $deployment = \App\Modules\Deployments\Models\Deployment::query()
                ->withoutGlobalScope('owned_by_organization')
                ->with('site.environment')
                ->whereKey($deploymentId)
                ->first();

            $isProduction = $deployment?->site?->environment?->is_production ?? false;
        }

        return [
            'reason' => $isProduction
                ? ['required', 'string', 'min:10']
                : ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) ? $reason : null;
    }
}
