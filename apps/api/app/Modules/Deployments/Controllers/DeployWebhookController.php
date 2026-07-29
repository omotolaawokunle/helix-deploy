<?php

declare(strict_types=1);

namespace App\Modules\Deployments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Deployments\Actions\ProcessDeployWebhookAction;
use App\Modules\Deployments\DTOs\WebhookPayload;
use App\Modules\Deployments\Exceptions\ConcurrentDeploymentException;
use App\Modules\Deployments\Exceptions\InvalidWebhookSignatureException;
use App\Modules\Deployments\Exceptions\NoBuildRunnerAvailableException;
use App\Modules\Deployments\Exceptions\WebhookIgnoredException;
use App\Modules\Deployments\Resources\DeploymentResource;
use App\Modules\Deployments\Services\Webhook\WebhookSignatureVerifier;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Modules\Sites\Services\SiteWebhookSecretService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

final class DeployWebhookController extends Controller
{
    public function store(
        string $webhookToken,
        Request $request,
        WebhookSignatureVerifier $signatureVerifier,
        SiteWebhookSecretService $webhookSecretService,
        ProcessDeployWebhookAction $processDeployWebhookAction,
    ): JsonResponse {
        $site = Site::query()
            ->withoutGlobalScope('owned_by_organization')
            ->with(['server', 'organization'])
            ->where('webhook_token', $webhookToken)
            ->first();

        if ($site === null) {
            throw (new ModelNotFoundException())->setModel(Site::class, [$webhookToken]);
        }

        $organization = $site->organization;

        if (! $organization instanceof Organization) {
            return response()->json(['message' => 'Site organization not found.'], 404);
        }

        $rawBody = $request->getContent();

        try {
            $secret = $webhookSecretService->decrypt($site, $organization);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'Webhook secret is not configured.'], 422);
        }

        try {
            $signatureVerifier->verify($request, $secret, $rawBody);
        } catch (InvalidWebhookSignatureException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } finally {
            sodium_memzero($secret);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook payload.'], 422);
        }

        try {
            $provider = $signatureVerifier->detectProvider($request);
            $parsed = $signatureVerifier->parserFor($provider)->parse($payload);
            $event = $this->resolveEventName($request, $provider, $parsed);
            $webhookPayload = new WebhookPayload(
                event: $event,
                branch: $parsed->branch,
                commitHash: $parsed->commitHash,
                commitMessage: $parsed->commitMessage,
            );
        } catch (InvalidWebhookSignatureException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        try {
            $deployment = $processDeployWebhookAction->execute($site, $webhookPayload);
        } catch (WebhookIgnoredException) {
            return response()->json(['ignored' => true], 200);
        } catch (ConcurrentDeploymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (NoBuildRunnerAvailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503)
                ->header('Retry-After', (string) $exception->retryAfterSeconds());
        }

        return DeploymentResource::make($deployment)
            ->additional([
                'channel' => 'deployment.'.$deployment->getKey(),
            ])
            ->response()
            ->setStatusCode(202);
    }

    private function resolveEventName(Request $request, string $provider, WebhookPayload $parsed): string
    {
        return match ($provider) {
            'github' => (string) $request->header('X-GitHub-Event', 'unknown'),
            'gitlab' => $parsed->event,
            'bitbucket' => (string) $request->header('X-Event-Key', 'unknown'),
            default => $parsed->event,
        };
    }
}
