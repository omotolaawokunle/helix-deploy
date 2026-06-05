<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Sites\Enums\GitProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'preDeployScript' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'postDeployScript' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'runMigrations' => ['sometimes', 'boolean'],
            'dockerImage' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dockerRegistry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dockerComposePath' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pipelineId' => ['sometimes', 'nullable', 'uuid'],
            'repositoryUrl' => ['sometimes', 'nullable', 'string', 'max:2048', 'url'],
            'repositoryProvider' => ['sometimes', 'nullable', 'string', Rule::enum(GitProvider::class)],
        ];
    }
}
