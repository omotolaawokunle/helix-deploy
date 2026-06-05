<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSiteRequest extends FormRequest
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
            'deployBranch' => ['sometimes', 'string', 'max:255'],
            'deployScript' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'runMigrations' => ['sometimes', 'boolean'],
            'dockerImage' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dockerRegistry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dockerComposePath' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pipelineId' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
