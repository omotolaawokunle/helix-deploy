<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Modules\Commands\Exceptions\DangerousCommandException;
use App\Modules\Credentials\Exceptions\CredentialAccessDeniedException;
use App\Modules\Deployments\Exceptions\ReleaseNotFoundException;
use App\Modules\Servers\Actions\ReportFingerprintMismatchAction;
use App\Packages\SSH\Exceptions\SSHConnectionException;
use App\Packages\SSH\Exceptions\SSHFingerprintMismatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LogicException;

final class HelixExceptionRenderer
{
    public function __construct(
        private readonly ReportFingerprintMismatchAction $reportFingerprintMismatch,
    ) {
    }

    public function render(SSHFingerprintMismatchException $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderJson($request)) {
            return null;
        }

        $this->reportFingerprintMismatch->execute($exception);

        return $this->jsonResponse(
            message: 'Server fingerprint has changed. Connection blocked. Verify the server has not been compromised, then re-verify in Settings.',
            code: 'SSH_FINGERPRINT_MISMATCH',
            status: 422,
        );
    }

    public function renderSshConnection(SSHConnectionException $exception, Request $request): ?JsonResponse
    {
        if ($exception instanceof SSHFingerprintMismatchException || ! $this->shouldRenderJson($request)) {
            return null;
        }

        $serverId = $this->extractServerId($exception);
        $ipAddress = $this->extractIpAddress($exception);

        Log::warning('SSH connection failed', array_filter([
            'server_id' => $serverId,
            'ip_address' => $ipAddress,
            'message' => $exception->getMessage(),
        ]));

        return $this->jsonResponse(
            message: 'Cannot connect to server. Check that the server is online and the SSH key is authorised.',
            code: 'SSH_CONNECTION_FAILED',
            status: 503,
        );
    }

    public function renderDangerousCommand(DangerousCommandException $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderJson($request)) {
            return null;
        }

        return $this->jsonResponse(
            message: $exception->getMessage(),
            code: 'DANGEROUS_COMMAND_BLOCKED',
            status: 422,
        );
    }

    public function renderCredentialAccessDenied(CredentialAccessDeniedException $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderJson($request)) {
            return null;
        }

        Log::warning('Credential access denied', [
            'message' => $exception->getMessage(),
            'user_id' => $request->user()?->getKey(),
            'path' => $request->path(),
        ]);

        return $this->jsonResponse(
            message: 'Access denied.',
            code: 'CREDENTIAL_ACCESS_DENIED',
            status: 403,
        );
    }

    public function renderReleaseNotFound(ReleaseNotFoundException $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRenderJson($request)) {
            return null;
        }

        return $this->jsonResponse(
            message: 'Release directory no longer exists on server.',
            code: 'RELEASE_NOT_FOUND',
            status: 404,
        );
    }

    public function renderAuditLogImmutable(LogicException $exception, Request $request): ?JsonResponse
    {
        if (! $this->isAuditLogImmutabilityViolation($exception) || ! $this->shouldRenderJson($request)) {
            return null;
        }

        Log::critical('Attempted to mutate audit log record', [
            'message' => $exception->getMessage(),
            'path' => $request->path(),
        ]);

        return $this->jsonResponse(
            message: 'An internal integrity error occurred.',
            code: 'AUDIT_LOG_IMMUTABLE',
            status: 500,
        );
    }

    private function shouldRenderJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private function isAuditLogImmutabilityViolation(LogicException $exception): bool
    {
        return in_array($exception->getMessage(), [
            'AuditLog records are immutable',
            'AuditLog records cannot be deleted',
        ], true);
    }

    private function extractServerId(SSHConnectionException $exception): ?string
    {
        if ($exception instanceof SSHFingerprintMismatchException) {
            return (string) $exception->server->getKey();
        }

        return null;
    }

    private function extractIpAddress(SSHConnectionException $exception): ?string
    {
        if ($exception instanceof SSHFingerprintMismatchException) {
            return $exception->server->ip_address;
        }

        return null;
    }

    private function jsonResponse(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
        ], $status);
    }
}
