<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\DigitalOcean;

use App\Modules\Integrations\Contracts\DnsZoneClientInterface;
use App\Modules\Integrations\DTOs\CloudflareDnsRecordDTO;
use App\Modules\Integrations\DTOs\CloudflareZoneDTO;
use App\Modules\Integrations\Exceptions\CloudflareApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class DigitalOceanDnsClient implements DnsZoneClientInterface
{
    private const string API_BASE = 'https://api.digitalocean.com/v2';

    private const int MAX_ATTEMPTS = 4;

    private const int INITIAL_BACKOFF_MS = 1000;

    private const int MAX_BACKOFF_MS = 8000;

    public function verifyToken(string $token): bool
    {
        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->get(self::API_BASE.'/account'),
            'Unable to verify DigitalOcean API token.',
        );

        return $response->successful();
    }

    public function listZones(string $token): array
    {
        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->get(self::API_BASE.'/domains', [
                    'per_page' => 200,
                ]),
            'Unable to list DigitalOcean domains.',
        );

        /** @var list<array<string, mixed>> $domains */
        $domains = data_get($response->json(), 'domains', []);

        return array_map(
            static fn (array $domain): CloudflareZoneDTO => CloudflareZoneDTO::fromDigitalOcean($domain),
            $domains,
        );
    }

    public function recordExists(string $token, string $zoneId, string $hostname): bool
    {
        return $this->findARecord($token, $zoneId, $hostname) !== null;
    }

    public function findARecord(string $token, string $zoneId, string $hostname): ?CloudflareDnsRecordDTO
    {
        $recordName = $this->recordNameForHostname($zoneId, $hostname);

        $response = $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->get(self::API_BASE.'/domains/'.$zoneId.'/records', [
                    'type' => 'A',
                    'name' => $recordName,
                ]),
            'Unable to check DigitalOcean DNS records.',
        );

        /** @var list<array<string, mixed>> $records */
        $records = data_get($response->json(), 'domain_records', []);

        if ($records === []) {
            return null;
        }

        return CloudflareDnsRecordDTO::fromDigitalOcean($records[0], $zoneId);
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
                ->post(self::API_BASE.'/domains/'.$zoneId.'/records', [
                    'type' => 'A',
                    'name' => $recordName,
                    'data' => $ipAddress,
                    'ttl' => 3600,
                ]),
            'Unable to create DigitalOcean DNS record.',
        );

        $recordId = data_get($response->json(), 'domain_record.id');

        if (! is_numeric($recordId)) {
            throw new CloudflareApiException('DigitalOcean did not return a DNS record ID.');
        }

        return (string) $recordId;
    }

    public function deleteRecord(string $token, string $zoneId, string $recordId): void
    {
        $this->send(
            fn (): Response => Http::withToken($token)
                ->acceptJson()
                ->delete(self::API_BASE.'/domains/'.$zoneId.'/records/'.$recordId),
            'Unable to delete DigitalOcean DNS record.',
        );
    }

    private function recordNameForHostname(string $zoneId, string $hostname): string
    {
        $hostname = strtolower($hostname);
        $zoneId = strtolower($zoneId);

        if ($hostname === $zoneId) {
            return '@';
        }

        $suffix = '.'.$zoneId;

        if (str_ends_with($hostname, $suffix)) {
            return substr($hostname, 0, -strlen($suffix));
        }

        return $hostname;
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

            if ($response->successful()) {
                return $response;
            }

            if ($attempt >= self::MAX_ATTEMPTS || ! $this->shouldRetry($response)) {
                $this->throwFromResponse($response, $message);
            }

            $this->sleep($backoffMs);
            $backoffMs = min($backoffMs * 2, self::MAX_BACKOFF_MS);
        }
    }

    private function shouldRetry(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds <= 0 || app()->runningUnitTests()) {
            return;
        }

        usleep($milliseconds * 1000);
    }

    private function throwFromResponse(Response $response, string $message): never
    {
        $detail = data_get($response->json(), 'message', $response->body());

        throw new CloudflareApiException(trim($message.' '.(is_string($detail) ? $detail : '')));
    }
}
