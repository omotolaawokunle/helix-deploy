<?php

declare(strict_types=1);

namespace App\Modules\Servers\DTOs;

use InvalidArgumentException;

readonly class AwsCloudCredentialDTO
{
    public function __construct(
        public string $accessKeyId,
        public string $secretAccessKey,
        public string $region,
    ) {
    }

    public function toJson(): string
    {
        return json_encode([
            'accessKeyId' => $this->accessKeyId,
            'secretAccessKey' => $this->secretAccessKey,
            'region' => $this->region,
        ], JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{accessKeyId?: string, secretAccessKey?: string, region?: string} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $accessKeyId = $decoded['accessKeyId'] ?? null;
        $secretAccessKey = $decoded['secretAccessKey'] ?? null;
        $region = $decoded['region'] ?? null;

        if (! is_string($accessKeyId) || $accessKeyId === ''
            || ! is_string($secretAccessKey) || $secretAccessKey === ''
            || ! is_string($region) || $region === '') {
            throw new InvalidArgumentException('Invalid AWS cloud provider credential payload.');
        }

        return new self($accessKeyId, $secretAccessKey, $region);
    }
}
