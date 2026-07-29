<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services\Webhook;

use App\Modules\Deployments\Contracts\WebhookPayloadParserInterface;
use App\Modules\Deployments\DTOs\WebhookPayload;

final class BitbucketWebhookParser implements WebhookPayloadParserInterface
{
    public function supports(string $providerKey): bool
    {
        return $providerKey === 'bitbucket';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload): WebhookPayload
    {
        $branch = null;
        $commitHash = null;
        $commitMessage = null;

        if (isset($payload['push']) && is_array($payload['push'])) {
            $changes = $payload['push']['changes'] ?? null;

            if (is_array($changes)) {
                foreach ($changes as $change) {
                    if (! is_array($change)) {
                        continue;
                    }

                    $new = $change['new'] ?? null;

                    if (! is_array($new) || ($new['type'] ?? null) !== 'branch') {
                        continue;
                    }

                    $branch = isset($new['name']) && is_string($new['name']) ? $new['name'] : null;
                    $target = $new['target'] ?? null;

                    if (is_array($target)) {
                        $commitHash = isset($target['hash']) && is_string($target['hash']) ? $target['hash'] : null;
                        $commitMessage = isset($target['message']) && is_string($target['message'])
                            ? $target['message']
                            : null;
                    }

                    break;
                }
            }
        }

        return new WebhookPayload(
            event: 'repo:push',
            branch: $branch,
            commitHash: $commitHash,
            commitMessage: $commitMessage,
        );
    }
}
