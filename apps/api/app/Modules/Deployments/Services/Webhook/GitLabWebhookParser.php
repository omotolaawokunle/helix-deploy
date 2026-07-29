<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services\Webhook;

use App\Modules\Deployments\Contracts\WebhookPayloadParserInterface;
use App\Modules\Deployments\DTOs\WebhookPayload;

final class GitLabWebhookParser implements WebhookPayloadParserInterface
{
    public function supports(string $providerKey): bool
    {
        return $providerKey === 'gitlab';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload): WebhookPayload
    {
        $objectKind = isset($payload['object_kind']) && is_string($payload['object_kind'])
            ? $payload['object_kind']
            : 'unknown';

        $ref = isset($payload['ref']) && is_string($payload['ref']) ? $payload['ref'] : null;
        $branch = $this->branchFromRef($ref);

        $commitHash = isset($payload['checkout_sha']) && is_string($payload['checkout_sha'])
            ? $payload['checkout_sha']
            : null;

        $commitMessage = null;

        if (isset($payload['commits']) && is_array($payload['commits']) && $payload['commits'] !== []) {
            $lastCommit = end($payload['commits']);

            if (is_array($lastCommit)) {
                $commitMessage = isset($lastCommit['message']) && is_string($lastCommit['message'])
                    ? $lastCommit['message']
                    : null;

                if ($commitHash === null && isset($lastCommit['id']) && is_string($lastCommit['id'])) {
                    $commitHash = $lastCommit['id'];
                }
            }
        }

        return new WebhookPayload(
            event: $objectKind,
            branch: $branch,
            commitHash: $commitHash,
            commitMessage: $commitMessage,
        );
    }

    private function branchFromRef(?string $ref): ?string
    {
        if ($ref === null || ! str_starts_with($ref, 'refs/heads/')) {
            return null;
        }

        return substr($ref, strlen('refs/heads/'));
    }
}
