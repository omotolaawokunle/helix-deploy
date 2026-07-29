<?php

declare(strict_types=1);

namespace App\Modules\Sites\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteWebhookSecretService;
use InvalidArgumentException;

final class RotateSiteWebhookSecretAction
{
    public function __construct(
        private readonly SiteWebhookSecretService $webhookSecretService,
    ) {
    }

    public function execute(Site $site, User $actor): string
    {
        if (! (bool) $site->auto_deploy_enabled) {
            throw new InvalidArgumentException('Auto deploy must be enabled before rotating the webhook secret.');
        }

        $organization = $site->organization;

        if (! $organization instanceof Organization) {
            throw new InvalidArgumentException('Site organization is required to rotate the webhook secret.');
        }

        if ($site->webhook_token === null) {
            $site->webhook_token = $this->webhookSecretService->generateWebhookToken();
            $site->save();
        }

        $plaintextSecret = $this->webhookSecretService->generatePlaintextSecret();
        $this->webhookSecretService->encryptAndStore($site, $organization, $plaintextSecret);

        AuditLog::record(
            operation: 'site.webhook_secret_rotated',
            resource: $site,
            beforeState: null,
            afterState: ['siteId' => $site->getKey()],
        );

        return $plaintextSecret;
    }
}
