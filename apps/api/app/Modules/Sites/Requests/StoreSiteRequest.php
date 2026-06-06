<?php

declare(strict_types=1);

namespace App\Modules\Sites\Requests;

use App\Modules\Integrations\Models\ProjectDnsZone;
use App\Modules\Integrations\Services\CloudflareHostnameResolver;
use App\Modules\Sites\DTOs\CreateSiteDTO;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\NodePM;
use App\Modules\Sites\Enums\PythonWSGI;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SslChallenge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
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
        $runtime = $this->input('runtime');
        $proxyRuntimes = [
            Runtime::NODEJS->value,
            Runtime::PYTHON->value,
            Runtime::GO->value,
            Runtime::DOCKER->value,
        ];

        return [
            'domain' => ['required_without:subdomainPrefix', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/i'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'webroot' => ['nullable', 'string', 'max:500'],
            'runtime' => ['required', 'string', Rule::in(array_column(Runtime::cases(), 'value'))],
            'deployMode' => ['nullable', 'string', Rule::in(array_column(DeployMode::cases(), 'value'))],
            'repositoryUrl' => ['nullable', 'string', 'max:500'],
            'repositoryProvider' => ['nullable', 'string', 'max:50'],
            'deployBranch' => ['nullable', 'string', 'max:255'],
            'preDeployScript' => ['nullable', 'string', 'max:65535'],
            'postDeployScript' => ['nullable', 'string', 'max:65535'],
            'runMigrations' => ['nullable', 'boolean'],
            'dockerImage' => ['nullable', 'string', 'max:255'],
            'dockerRegistry' => ['nullable', 'string', 'max:255'],
            'dockerComposePath' => ['nullable', 'string', 'max:255'],
            'dockerBuildMode' => ['nullable', 'string', Rule::in(array_column(DockerBuildMode::cases(), 'value'))],
            'phpVersion' => ['nullable', 'string', 'max:10', Rule::requiredIf($runtime === Runtime::PHP->value)],
            'nodePm' => ['nullable', 'string', Rule::in(array_column(NodePM::cases(), 'value'))],
            'pythonWsgi' => ['nullable', 'string', Rule::in(array_column(PythonWSGI::cases(), 'value'))],
            'goBinaryPath' => ['nullable', 'string', 'max:500'],
            'goServiceName' => ['nullable', 'string', 'max:255'],
            'appPort' => ['nullable', 'integer', 'min:1', 'max:65535', Rule::requiredIf(in_array($runtime, $proxyRuntimes, true))],
            'projectId' => ['nullable', 'uuid', 'exists:projects,id'],
            'environmentId' => ['nullable', 'uuid', 'exists:environments,id'],
            'pipelineId' => ['nullable', 'uuid'],
            'autoCreateDns' => ['nullable', 'boolean'],
            'enableSsl' => ['nullable', 'boolean'],
            'projectDnsZoneId' => ['nullable', 'uuid', 'exists:project_dns_zones,id'],
            'subdomainPrefix' => ['nullable', 'string', 'max:255', 'regex:/^(@|[a-z0-9]([a-z0-9\-]*[a-z0-9])?)$/i'],
            'includeWwwAlias' => ['nullable', 'boolean'],
            'sslChallenge' => ['nullable', 'string', Rule::in(array_column(SslChallenge::cases(), 'value'))],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ((bool) $this->input('autoCreateDns', false) && $this->input('projectId') === null) {
                $validator->errors()->add('projectId', 'A project is required when auto-create DNS is enabled.');
            }

            if ((bool) $this->input('autoCreateDns', false) && $this->input('projectDnsZoneId') === null) {
                $validator->errors()->add('projectDnsZoneId', 'A project DNS zone is required when auto-create DNS is enabled.');
            }
        });
    }

    public function toDto(): CreateSiteDTO
    {
        $validated = $this->validated();
        $runtime = Runtime::from((string) $validated['runtime']);
        $domain = $this->resolveDomain($validated);
        $aliases = array_values($validated['aliases'] ?? []);

        $webroot = isset($validated['webroot']) && is_string($validated['webroot']) && $validated['webroot'] !== ''
            ? $validated['webroot']
            : match ($runtime) {
                Runtime::PHP => '/var/www/'.$domain.'/current/public',
                default => '/var/www/'.$domain.'/current',
            };

        return new CreateSiteDTO(
            domain: $domain,
            aliases: $aliases,
            webroot: $webroot,
            runtime: $runtime,
            deployMode: DeployMode::from((string) ($validated['deployMode'] ?? DeployMode::GIT->value)),
            repositoryUrl: isset($validated['repositoryUrl']) ? (string) $validated['repositoryUrl'] : null,
            repositoryProvider: isset($validated['repositoryProvider']) ? (string) $validated['repositoryProvider'] : null,
            deployBranch: (string) ($validated['deployBranch'] ?? 'main'),
            preDeployScript: isset($validated['preDeployScript']) ? (string) $validated['preDeployScript'] : null,
            postDeployScript: isset($validated['postDeployScript']) ? (string) $validated['postDeployScript'] : null,
            runMigrations: (bool) ($validated['runMigrations'] ?? true),
            dockerImage: isset($validated['dockerImage']) ? (string) $validated['dockerImage'] : null,
            dockerRegistry: isset($validated['dockerRegistry']) ? (string) $validated['dockerRegistry'] : null,
            dockerComposePath: (string) ($validated['dockerComposePath'] ?? 'docker-compose.yml'),
            dockerBuildMode: isset($validated['dockerBuildMode'])
                ? DockerBuildMode::from((string) $validated['dockerBuildMode'])
                : null,
            phpVersion: isset($validated['phpVersion']) ? (string) $validated['phpVersion'] : null,
            nodePm: isset($validated['nodePm']) ? NodePM::from((string) $validated['nodePm']) : null,
            pythonWsgi: isset($validated['pythonWsgi']) ? PythonWSGI::from((string) $validated['pythonWsgi']) : null,
            goBinaryPath: isset($validated['goBinaryPath']) ? (string) $validated['goBinaryPath'] : null,
            goServiceName: isset($validated['goServiceName']) ? (string) $validated['goServiceName'] : null,
            appPort: isset($validated['appPort']) ? (int) $validated['appPort'] : null,
            projectId: isset($validated['projectId']) ? (string) $validated['projectId'] : null,
            environmentId: isset($validated['environmentId']) ? (string) $validated['environmentId'] : null,
            pipelineId: isset($validated['pipelineId']) ? (string) $validated['pipelineId'] : null,
            autoCreateDns: (bool) ($validated['autoCreateDns'] ?? false),
            enableSsl: (bool) ($validated['enableSsl'] ?? false),
            projectDnsZoneId: isset($validated['projectDnsZoneId']) ? (string) $validated['projectDnsZoneId'] : null,
            includeWwwAlias: (bool) ($validated['includeWwwAlias'] ?? false),
            sslChallenge: isset($validated['sslChallenge'])
                ? SslChallenge::from((string) $validated['sslChallenge'])
                : null,
        );
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveDomain(array $validated): string
    {
        if (isset($validated['subdomainPrefix'], $validated['projectDnsZoneId'])) {
            $projectDnsZone = ProjectDnsZone::query()
                ->withoutGlobalScope('owned_by_organization')
                ->whereKey((string) $validated['projectDnsZoneId'])
                ->first();

            if ($projectDnsZone !== null) {
                /** @var CloudflareHostnameResolver $resolver */
                $resolver = app(CloudflareHostnameResolver::class);

                return $resolver->buildFromPrefix(
                    (string) $validated['subdomainPrefix'],
                    $projectDnsZone->base_domain,
                );
            }
        }

        return (string) $validated['domain'];
    }
}
