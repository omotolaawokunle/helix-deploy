<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function branch(): ?string
    {
        $branch = $this->validated('branch');

        return is_string($branch) && $branch !== '' ? $branch : null;
    }
}
