<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\DTOs\UpdateSiteAutoDeployResult;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteWebhookSecretService;
use InvalidArgumentException;

final class UpdateSiteAutoDeployAction
{
    public function __construct(
        private readonly SiteWebhookSecretService $webhookSecretService,
    ) {
    }

    public function execute(Site $site, User $actor, bool $enabled): UpdateSiteAutoDeployResult
    {
        if ($enabled && ! $site->canAutoDeploy()) {
            throw new InvalidArgumentException(
                'Auto deploy requires git deploy mode, or docker build mode with a repository URL and deploy branch.',
            );
        }

        $organization = $site->organization;

        if (! $organization instanceof Organization) {
            throw new InvalidArgumentException('Site organization is required to configure auto deploy.');
        }

        $beforeEnabled = (bool) $site->auto_deploy_enabled;
        $revealedSecret = null;

        if ($enabled) {
            if ($site->webhook_token === null) {
                $site->webhook_token = $this->webhookSecretService->generateWebhookToken();
            }

            if (! $this->webhookSecretService->hasSecret($site)) {
                $revealedSecret = $this->webhookSecretService->generatePlaintextSecret();
                $this->webhookSecretService->encryptAndStore($site, $organization, $revealedSecret);
            }

            $site->auto_deploy_enabled = true;
        } else {
            $site->auto_deploy_enabled = false;
        }

        $site->save();

        if ($beforeEnabled !== $enabled) {
            AuditLog::record(
                operation: 'site.auto_deploy_enabled',
                resource: $site,
                beforeState: ['autoDeployEnabled' => $beforeEnabled],
                afterState: ['autoDeployEnabled' => $enabled],
            );
        }

        return new UpdateSiteAutoDeployResult(
            site: $site->refresh(),
            revealedWebhookSecret: $revealedSecret,
        );
    }
}
