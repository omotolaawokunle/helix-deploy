<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\BuildRunners\Enums\BuildStrategy;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\GitProvider;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Models\Site;
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
            'preBuildScript' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'buildStrategy' => ['sometimes', 'string', Rule::enum(BuildStrategy::class)],
            'buildRunnerId' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('build_runners', 'id')->where(
                    fn ($query) => $query->where(
                        'organization_id',
                        (string) $this->user()?->currentOrganization()?->getKey(),
                    ),
                ),
            ],
            'runMigrations' => ['sometimes', 'boolean'],
            'deployMode' => ['sometimes', 'string', Rule::enum(DeployMode::class)],
            'dockerBuildMode' => [
                'sometimes',
                'nullable',
                'string',
                Rule::enum(DockerBuildMode::class),
                Rule::requiredIf(fn (): bool => $this->input('deployMode') === DeployMode::DOCKER->value),
            ],
            'dockerImage' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dockerRegistry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dockerComposePath' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pipelineId' => ['sometimes', 'nullable', 'uuid'],
            'repositoryUrl' => ['sometimes', 'nullable', 'string', 'max:2048', 'url'],
            'repositoryProvider' => ['sometimes', 'nullable', 'string', Rule::enum(GitProvider::class)],
            'autoDeployEnabled' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($this->input('deployMode') !== DeployMode::DOCKER->value) {
                return;
            }

            $site = $this->resolveSite();

            if ($site === null || $site->runtime !== Runtime::DOCKER) {
                $validator->errors()->add(
                    'deployMode',
                    'Docker deploy mode requires the docker runtime.',
                );
            }
        });
    }

    private function resolveSite(): ?Site
    {
        $siteId = $this->route('site');

        if (! is_string($siteId) || $siteId === '') {
            return null;
        }

        return Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->whereKey($siteId)
            ->first();
    }
}
