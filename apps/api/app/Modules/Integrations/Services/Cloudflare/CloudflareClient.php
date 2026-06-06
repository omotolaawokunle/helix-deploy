<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Cloudflare;

use App\Modules\Integrations\Contracts\CloudflareClientInterface;
use App\Modules\Integrations\DTOs\CloudflareDnsRecordDTO;
use App\Modules\Integrations\DTOs\CloudflareZoneDTO;
use App\Modules\Integrations\Exceptions\CloudflareApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class CloudflareClient implements CloudflareClientInterface
{
    private const string API_BASE = 'https://api.cloudflare.com/client/v4';

    private const int MAX_ATTEMPTS = 4;

    private const int INITIAL_BACKOFF_MS = 1000;

    private const int MAX_BACKOFF_MS = 8000;

    public function verifyToken(string $token): bool
    {
        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->get(self::API_BASE.'/user/tokens/verify'),
            'Unable to verify Cloudflare API token.',
        );

        return (bool) data_get($response->json(), 'success', false);
    }

    public function listZones(string $token): array
    {
        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->get(self::API_BASE.'/zones', [
                    'per_page' => 50,
                ]),
            'Unable to list Cloudflare zones.',
        );

        /** @var list<array<string, mixed>> $zones */
        $zones = data_get($response->json(), 'result', []);

        return array_map(
            static fn (array $zone): CloudflareZoneDTO => CloudflareZoneDTO::fromCloudflare($zone),
            $zones,
        );
    }

    public function recordExists(string $token, string $zoneId, string $hostname): bool
    {
        return $this->findARecord($token, $zoneId, $hostname) !== null;
    }

    public function findARecord(string $token, string $zoneId, string $hostname): ?CloudflareDnsRecordDTO
    {
        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->get(self::API_BASE.'/zones/'.$zoneId.'/dns_records', [
                    'name' => $hostname,
                    'type' => 'A',
                    'per_page' => 1,
                ]),
            'Unable to check Cloudflare DNS records.',
        );

        /** @var list<array<string, mixed>> $records */
        $records = data_get($response->json(), 'result', []);

        if ($records === []) {
            return null;
        }

        return CloudflareDnsRecordDTO::fromCloudflare($records[0]);
    }

    public function createARecord(
        string $token,
        string $zoneId,
        string $recordName,
        string $ipAddress,
        bool $proxied = false,
    ): string {
        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->post(self::API_BASE.'/zones/'.$zoneId.'/dns_records', [
                    'type' => 'A',
                    'name' => $recordName,
                    'content' => $ipAddress,
                    'proxied' => $proxied,
                ]),
            'Unable to create Cloudflare DNS record.',
        );

        $recordId = data_get($response->json(), 'result.id');

        if (! is_string($recordId) || $recordId === '') {
            throw new CloudflareApiException('Cloudflare did not return a DNS record ID.');
        }

        return $recordId;
    }

    public function deleteRecord(string $token, string $zoneId, string $recordId): void
    {
        $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->delete(self::API_BASE.'/zones/'.$zoneId.'/dns_records/'.$recordId),
            'Unable to delete Cloudflare DNS record.',
        );
    }

    /**
     * @param callable(): Response $request
     */
    private function send(callable $request, string $message): Response
    {
        $attempt = 0;
        $backoffMs = self::INITIAL_BACKOFF_MS;

        while (true) {
            $attempt++;

            try {
                $response = $request();
            } catch (ConnectionException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new CloudflareApiException(trim($message.' '.$exception->getMessage()), previous: $exception);
                }

                $this->sleep($backoffMs);
                $backoffMs = min($backoffMs * 2, self::MAX_BACKOFF_MS);

                continue;
            }

            if ($this->isSuccessful($response)) {
                return $response;
            }

            if ($attempt >= self::MAX_ATTEMPTS || ! $this->shouldRetry($response)) {
                $this->throwFromResponse($response, $message);
            }

            $retryAfterMs = $this->retryAfterMilliseconds($response);

            $this->sleep($retryAfterMs ?? $backoffMs);
            $backoffMs = min($backoffMs * 2, self::MAX_BACKOFF_MS);
        }
    }

    private function isSuccessful(Response $response): bool
    {
        return $response->successful() && (bool) data_get($response->json(), 'success', false);
    }

    private function shouldRetry(Response $response): bool
    {
        if ($response->status() === 429) {
            return true;
        }

        return $response->serverError();
    }

    private function retryAfterMilliseconds(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        if ($header === null || $header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return max(0, (int) $header) * 1000;
        }

        $retryAt = strtotime($header);

        if ($retryAt === false) {
            return null;
        }

        return max(0, ($retryAt - time()) * 1000);
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        if (app()->runningUnitTests()) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    private function throwFromResponse(Response $response, string $message): never
    {
        $errors = data_get($response->json(), 'errors', []);
        $detail = is_array($errors) && isset($errors[0]['message'])
            ? (string) $errors[0]['message']
            : $response->body();

        throw new CloudflareApiException(trim($message.' '.$detail));
    }
}
