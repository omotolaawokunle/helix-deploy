<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Deployments\Enums\DeploymentStatus;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Jobs\RunDeploymentJob;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pipelines\Enums\PipelineStepType;
use App\Modules\Pipelines\Jobs\RunPipelineJob;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineRun;
use App\Modules\Pipelines\Models\PipelineStep;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\DockerBuildMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteWebhookSecretService;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('triggers deployment from a valid github webhook push', function (): void {
    Queue::fake();

    [$site, $organization, $token, $secret] = autoDeployWebhookFixture();

    $payload = githubWebhookPayload(branch: 'main', commit: 'abc123def456');

    $response = postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ]);

    $response->assertAccepted()
        ->assertJsonPath('data.triggerType', TriggerType::WEBHOOK->value)
        ->assertJsonPath('data.commitHash', 'abc123def456');

    Queue::assertPushed(RunDeploymentJob::class);

    expect(Deployment::query()->withoutGlobalScope('owned_by_organization')->count())->toBe(1);

    expect(AuditLog::query()->where('operation', 'deployment.triggered')->exists())->toBeTrue();
});

it('triggers deployment from webhook for docker build mode site', function (): void {
    Queue::fake();

    [, , $token, $secret] = autoDeployWebhookFixture(
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::BUILD,
    );

    $payload = githubWebhookPayload(branch: 'main', commit: 'abc123def456');

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ])->assertAccepted();

    Queue::assertPushed(RunDeploymentJob::class);
});

it('ignores webhook for docker pull mode site that is no longer eligible', function (): void {
    Queue::fake();

    [$site, , $token, $secret] = autoDeployWebhookFixture(
        deployMode: DeployMode::DOCKER,
        dockerBuildMode: DockerBuildMode::BUILD,
    );

    $site->forceFill([
        'docker_build_mode' => DockerBuildMode::PULL->value,
    ])->save();

    $payload = githubWebhookPayload();

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ])->assertOk()
        ->assertJsonPath('ignored', true);

    Queue::assertNothingPushed();
});

it('rejects github webhook with invalid signature', function (): void {
    Queue::fake();

    [, , $token] = autoDeployWebhookFixture();
    $payload = githubWebhookPayload();

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('ignores webhook push to non matching branch', function (): void {
    Queue::fake();

    [, , $token, $secret] = autoDeployWebhookFixture();
    $payload = githubWebhookPayload(branch: 'develop');

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ])->assertOk()
        ->assertJsonPath('ignored', true);

    Queue::assertNothingPushed();
});

it('ignores webhook when auto deploy is disabled', function (): void {
    Queue::fake();

    [$site, $organization, $token, $secret] = autoDeployWebhookFixture();
    $site->forceFill(['auto_deploy_enabled' => false])->save();

    $payload = githubWebhookPayload();

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ])->assertOk()
        ->assertJsonPath('ignored', true);

    Queue::assertNothingPushed();
});

it('returns conflict when deployment already in progress', function (): void {
    [$site, , $token, $secret] = autoDeployWebhookFixture();

    Deployment::query()->withoutGlobalScope('owned_by_organization')->create([
        'site_id' => (string) $site->getKey(),
        'organization_id' => (string) $site->organization_id,
        'type' => \App\Modules\Deployments\Enums\DeploymentType::DEPLOY,
        'status' => DeploymentStatus::RUNNING,
        'trigger_type' => TriggerType::MANUAL,
        'branch' => 'main',
    ]);

    $payload = githubWebhookPayload();

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ])->assertStatus(409);
});

it('returns not found for unknown webhook token', function (): void {
    $payload = githubWebhookPayload();

    postDeployWebhook('unknown-token', $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertNotFound();
});

it('triggers pipeline run for pipeline linked site on webhook', function (): void {
    Queue::fake();

    [$site, $organization, $token, $secret, $owner] = autoDeployWebhookFixture(withPipeline: true);
    $payload = githubWebhookPayload();

    postDeployWebhook($token, $payload, [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => githubSignature($payload, $secret),
    ])->assertAccepted();

    Queue::assertPushed(RunPipelineJob::class);

    expect(PipelineRun::query()->withoutGlobalScope('owned_by_organization')->count())->toBe(1);

    $deployment = Deployment::query()->withoutGlobalScope('owned_by_organization')->first();
    expect($deployment?->trigger_type)->toBe(TriggerType::WEBHOOK);
    expect($deployment?->triggered_by)->toBeNull();
});

it('accepts valid gitlab webhook token', function (): void {
    Queue::fake();

    [, , $token, $secret] = autoDeployWebhookFixture();
    $payload = gitlabWebhookPayload();

    postDeployWebhook($token, $payload, [
        'X-Gitlab-Event' => 'Push Hook',
        'X-Gitlab-Token' => $secret,
    ])->assertAccepted();

    Queue::assertPushed(RunDeploymentJob::class);
});

/**
 * @return array{0: Site, 1: Organization, 2: string, 3: string, 4?: User}
 */
function autoDeployWebhookFixture(
    bool $withPipeline = false,
    DeployMode $deployMode = DeployMode::GIT,
    ?DockerBuildMode $dockerBuildMode = null,
): array {
    $organization = Organization::query()->create([
        'name' => 'Webhook Org',
        'slug' => 'webhook-org-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'webhook.test',
        'ip_address' => '10.0.0.20',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [],
    ]);

    $pipelineId = null;

    if ($withPipeline) {
        $pipeline = Pipeline::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'name' => 'Webhook Pipeline',
            'description' => null,
            'stages' => [],
            'created_by' => (string) $owner->getKey(),
        ]);

        PipelineStep::query()->create([
            'pipeline_id' => (string) $pipeline->getKey(),
            'name' => 'Deploy',
            'type' => PipelineStepType::DEPLOY,
            'order' => 0,
            'config' => [],
            'requires_approval' => false,
            'retry_attempts' => 0,
        ]);

        $pipelineId = (string) $pipeline->getKey();
    }

    $site = Site::query()->withoutGlobalScope('owned_by_organization')->create([
        'server_id' => (string) $server->getKey(),
        'organization_id' => (string) $organization->getKey(),
        'domain' => 'webhook.example.test',
        'aliases' => [],
        'webroot' => '/var/www/webhook.example.test/current/public',
        'runtime' => $deployMode === DeployMode::DOCKER ? Runtime::DOCKER : Runtime::PHP,
        'deploy_mode' => $deployMode,
        'docker_build_mode' => $dockerBuildMode?->value,
        'repository_url' => 'git@github.com:helix/example.git',
        'repository_provider' => 'github',
        'deploy_branch' => 'main',
        'run_migrations' => false,
        'status' => SiteStatus::ACTIVE,
        'pipeline_id' => $pipelineId,
    ]);

    $secret = 'webhook-test-secret-'.Str::random(8);
    $token = bin2hex(random_bytes(16));

    app(SiteWebhookSecretService::class)->encryptAndStore($site, $organization, $secret);

    $site->forceFill([
        'auto_deploy_enabled' => true,
        'webhook_token' => $token,
    ])->save();

    return [$site->refresh(), $organization, $token, $secret, $owner];
}

function githubWebhookPayload(string $branch = 'main', string $commit = 'abc123def456'): string
{
    return json_encode([
        'ref' => 'refs/heads/'.$branch,
        'after' => $commit,
        'head_commit' => [
            'id' => $commit,
            'message' => 'Deploy from webhook',
        ],
    ], JSON_THROW_ON_ERROR);
}

function gitlabWebhookPayload(string $branch = 'main', string $commit = 'abc123def456'): string
{
    return json_encode([
        'object_kind' => 'push',
        'ref' => 'refs/heads/'.$branch,
        'checkout_sha' => $commit,
        'commits' => [
            [
                'id' => $commit,
                'message' => 'Deploy from webhook',
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

function githubSignature(string $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $payload, $secret);
}

/**
 * @param array<string, string> $headers
 */
function postDeployWebhook(string $token, string $payload, array $headers = []): \Illuminate\Testing\TestResponse
{
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ];

    foreach ($headers as $key => $value) {
        $server['HTTP_'.str_replace('-', '_', strtoupper($key))] = $value;
    }

    return test()->call(
        'POST',
        '/api/v1/hooks/sites/'.$token,
        [],
        [],
        [],
        $server,
        $payload,
    );
}
