<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Contracts;

use App\Modules\Deployments\DTOs\WebhookPayload;

interface WebhookPayloadParserInterface
{
    public function supports(string $providerKey): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload): WebhookPayload;
}
