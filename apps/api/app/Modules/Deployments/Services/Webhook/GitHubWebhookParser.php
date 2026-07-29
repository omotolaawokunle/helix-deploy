<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services\Webhook;

use App\Modules\Deployments\Contracts\WebhookPayloadParserInterface;
use App\Modules\Deployments\DTOs\WebhookPayload;

final class GitHubWebhookParser implements WebhookPayloadParserInterface
{
    public function supports(string $providerKey): bool
    {
        return $providerKey === 'github';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload): WebhookPayload
    {
        $ref = isset($payload['ref']) && is_string($payload['ref']) ? $payload['ref'] : null;
        $branch = $this->branchFromRef($ref);
        $commitHash = isset($payload['after']) && is_string($payload['after']) ? $payload['after'] : null;

        if ($commitHash === '0000000000000000000000000000000000000000') {
            $commitHash = null;
        }

        $commitMessage = null;

        if (isset($payload['head_commit']) && is_array($payload['head_commit'])) {
            $headCommit = $payload['head_commit'];
            $commitMessage = isset($headCommit['message']) && is_string($headCommit['message'])
                ? $headCommit['message']
                : null;

            if ($commitHash === null && isset($headCommit['id']) && is_string($headCommit['id'])) {
                $commitHash = $headCommit['id'];
            }
        }

        return new WebhookPayload(
            event: 'push',
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
