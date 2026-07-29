<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Actions;

use App\Modules\Deployments\DTOs\TriggerDeploymentDTO;
use App\Modules\Deployments\DTOs\WebhookPayload;
use App\Modules\Deployments\Enums\TriggerType;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Exceptions\NoBuildRunnerAvailableException;
use App\Modules\Deployments\Exceptions\WebhookIgnoredException;
use App\Modules\Deployments\Models\Deployment;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class ProcessDeployWebhookAction
{
    public function __construct(
        private readonly TriggerDeploymentAction $triggerDeploymentAction,
    ) {
    }

    public function execute(Site $site, WebhookPayload $payload): Deployment
    {
        if (! (bool) $site->auto_deploy_enabled) {
            throw new WebhookIgnoredException('Auto deploy is disabled for this site.');
        }

        if (! $payload->isPushEvent()) {
            throw new WebhookIgnoredException('Webhook event is not a push.');
        }

        if ($payload->branch === null || $payload->branch !== $site->deploy_branch) {
            throw new WebhookIgnoredException('Push branch does not match deploy branch.');
        }

        if ($site->status !== SiteStatus::ACTIVE) {
            Log::warning('Deploy webhook ignored for inactive site.', ['siteId' => $site->getKey()]);

            throw new WebhookIgnoredException('Site is not active.');
        }

        $server = $site->server;

        if ($server === null || ! $server->isManaged()) {
            Log::warning('Deploy webhook ignored for unmanaged server.', ['siteId' => $site->getKey()]);

            throw new WebhookIgnoredException('Site server is not managed.');
        }

        try {
            return $this->triggerDeploymentAction->execute(
                site: $site,
                actor: null,
                dto: new TriggerDeploymentDTO(
                    branch: $payload->branch,
                    triggerType: TriggerType::WEBHOOK,
                    commitHash: $payload->commitHash,
                    commitMessage: $payload->commitMessage,
                ),
            );
        } catch (ConcurrentDeploymentException $exception) {
            throw $exception;
        } catch (NoBuildRunnerAvailableException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            throw new WebhookIgnoredException($exception->getMessage(), previous: $exception);
        }
    }
}
