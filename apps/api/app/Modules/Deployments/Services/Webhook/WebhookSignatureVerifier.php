<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Services\Webhook;

use App\Modules\Deployments\Contracts\WebhookPayloadParserInterface;
use App\Modules\Deployments\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Http\Request;

final class WebhookSignatureVerifier
{
    /**
     * @param iterable<WebhookPayloadParserInterface> $parsers
     */
    public function __construct(
        private readonly iterable $parsers,
    ) {
    }

    public function detectProvider(Request $request): string
    {
        if ($request->headers->has('X-GitHub-Event')) {
            return 'github';
        }

        if ($request->headers->has('X-Gitlab-Event') || $request->headers->has('X-Gitlab-Token')) {
            return 'gitlab';
        }

        if ($request->headers->has('X-Event-Key')) {
            return 'bitbucket';
        }

        throw new InvalidWebhookSignatureException('Unable to detect webhook provider.');
    }

    public function verify(Request $request, string $secret, string $rawBody): void
    {
        $provider = $this->detectProvider($request);

        match ($provider) {
            'github' => $this->verifyGitHub($request, $secret, $rawBody),
            'gitlab' => $this->verifyGitLab($request, $secret),
            'bitbucket' => $this->verifyBitbucket($request, $secret, $rawBody),
            default => throw new InvalidWebhookSignatureException('Unsupported webhook provider.'),
        };
    }

    public function parserFor(string $providerKey): WebhookPayloadParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($providerKey)) {
                return $parser;
            }
        }

        throw new InvalidWebhookSignatureException('No parser registered for provider: '.$providerKey);
    }

    private function verifyGitHub(Request $request, string $secret, string $rawBody): void
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($signature === '' || ! str_starts_with($signature, 'sha256=')) {
            throw new InvalidWebhookSignatureException('Missing GitHub webhook signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException('Invalid GitHub webhook signature.');
        }
    }

    private function verifyGitLab(Request $request, string $secret): void
    {
        $token = (string) $request->header('X-Gitlab-Token', '');

        if ($token === '' || ! hash_equals($secret, $token)) {
            throw new InvalidWebhookSignatureException('Invalid GitLab webhook token.');
        }
    }

    private function verifyBitbucket(Request $request, string $secret, string $rawBody): void
    {
        $signature = (string) $request->header('X-Hub-Signature', '');

        if ($signature === '') {
            throw new InvalidWebhookSignatureException('Missing Bitbucket webhook signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhookSignatureException('Invalid Bitbucket webhook signature.');
        }
    }
}
