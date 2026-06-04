<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\DeploymentType;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Projects\Models\Environment;
use App\Modules\Projects\Models\Project;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\NodePM;
use App\Modules\Sites\Enums\PythonWSGI;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Packages\Execution\DeploymentContext;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Str;

/**
 * @param array<string, mixed> $siteOverrides
 * @return array{0: Organization, 1: Server, 2: Site, 3: Deployment}
 */
function executionFixture(Runtime $runtime = Runtime::PHP, array $siteOverrides = []): array
{
    $organization = Organization::query()->create([
        'name' => 'Execution Test Org',
        'slug' => 'execution-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'execution.test',
        'ip_address' => '127.0.0.1',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $user->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $project = Project::query()->create([
        'organization_id' => (string) $organization->getKey(),
        'name' => 'Execution Project',
    ]);

    $environment = Environment::query()->create([
        'project_id' => (string) $project->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'name' => 'production',
        'label' => 'Production',
        'is_production' => true,
    ]);

    $domain = 'app.example.test';

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create(array_merge([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'project_id' => (string) $project->getKey(),
        'environment_id' => (string) $environment->getKey(),
        'domain' => $domain,
        'aliases' => [],
        'webroot' => '/var/www/'.$domain.'/current/public',
        'runtime' => $runtime,
        'deploy_mode' => DeployMode::GIT,
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'deploy_script' => null,
        'run_migrations' => true,
        'docker_image' => null,
        'docker_registry' => null,
        'docker_compose_path' => 'docker-compose.yml',
        'docker_build_mode' => null,
        'php_version' => '8.3',
        'node_pm' => NodePM::PM2,
        'python_wsgi' => PythonWSGI::GUNICORN,
        'go_binary_path' => '/usr/local/bin/app.example.test',
        'go_service_name' => 'app.example.test',
        'status' => SiteStatus::ACTIVE,
    ], $siteOverrides));

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'type' => DeploymentType::DEPLOY,
        'status' => DeploymentStatus::RUNNING,
        'triggered_by' => (string) $user->getKey(),
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    return [$organization, $server, $site, $deployment];
}

function executionContext(
    Site $site,
    Deployment $deployment,
    Server $server,
    FakeSSHConnection $ssh,
): DeploymentContext {
    return DeploymentContext::forDeployment($deployment, $site, $server, $ssh);
}

function sshSuccess(string $stdout = 'ok', int $exitCode = 0): SSHResult
{
    return new SSHResult('cmd', $exitCode, $stdout, '', 0.01);
}

function sshFailure(string $stderr = 'failed'): SSHResult
{
    return new SSHResult('cmd', 1, '', $stderr, 0.01);
}

function fakeSsh(): FakeSSHConnection
{
    return (new FakeSSHConnection())->connect();
}

/**
 * @param array<string, SSHResult|list<SSHResult>> $responses
 */
function queueSshResponses(FakeSSHConnection $ssh, array $responses): void
{
    foreach ($responses as $pattern => $result) {
        if ($result instanceof SSHResult) {
            $ssh->addSequence($pattern, $result);

            continue;
        }

        $ssh->addSequence($pattern, ...$result);
    }
}
