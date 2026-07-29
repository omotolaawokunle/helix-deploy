<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Sites\Enums\GitProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClaimSiteRequest extends FormRequest
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
            'repositoryUrl' => ['required', 'string', 'url', 'max:2048'],
            'repositoryProvider' => ['required', 'string', Rule::enum(GitProvider::class)],
            'deployBranch' => ['required', 'string', 'max:255'],
            'autoDeployEnabled' => ['sometimes', 'boolean'],
        ];
    }
}
