<?php

declare(strict_types=1);

namespace App\Modules\Deployments\DTOs;

final readonly class WebhookPayload
{
    public function __construct(
        public string $event,
        public ?string $branch,
        public ?string $commitHash,
        public ?string $commitMessage,
    ) {
    }

    public function isPushEvent(): bool
    {
        return in_array($this->event, ['push', 'repo:push'], true);
    }
}
