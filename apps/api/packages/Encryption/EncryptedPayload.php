<?php

declare(strict_types=1);

namespace App\Packages\Encryption;

use InvalidArgumentException;
use JsonException;

readonly class EncryptedPayload
{
    public function __construct(
        public string $ciphertext,
        public string $nonce,
    ) {
    }

    /**
     * @return array{ciphertext:string,nonce:string}
     */
    public function toArray(): array
    {
        return [
            'ciphertext' => $this->ciphertext,
            'nonce' => $this->nonce,
        ];
    }

    /**
     * @param array{ciphertext?:mixed,nonce?:mixed} $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['ciphertext']) || ! is_string($data['ciphertext'])) {
            throw new InvalidArgumentException('Encrypted payload ciphertext must be a string.');
        }

        if (! isset($data['nonce']) || ! is_string($data['nonce'])) {
            throw new InvalidArgumentException('Encrypted payload nonce must be a string.');
        }

        return new self(
            ciphertext: $data['ciphertext'],
            nonce: $data['nonce'],
        );
    }

    public function toJson(): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                previous: $exception,
                message: 'Failed to serialize encrypted payload to JSON.',
            );
        }
    }

    public static function fromJson(string $json): self
    {
        try {
            /** @var array{ciphertext?:mixed,nonce?:mixed} $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                previous: $exception,
                message: 'Invalid encrypted payload JSON provided.',
            );
        }

        return self::fromArray($data);
    }
}
