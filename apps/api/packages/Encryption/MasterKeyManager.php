<?php

declare(strict_types=1);

namespace App\Packages\Encryption;

use App\Packages\Encryption\Contracts\EncryptionInterface;
use App\Packages\Encryption\Exceptions\InvalidKeyException;

class MasterKeyManager
{
    public function __construct(
        private readonly EncryptionInterface $encryption,
        private readonly string|null $appKey = null,
    ) {
    }

    public function deriveAppKey(): string
    {
        $appKey = $this->appKey ?? (string) config('app.key', '');

        if ($appKey === '') {
            throw new InvalidKeyException('APP_KEY is missing and cannot derive encryption key.');
        }

        $rawAppKey = $this->normalizeAppKey($appKey);
        $derivedKey = sodium_crypto_generichash(
            $rawAppKey,
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        );

        return base64_encode($derivedKey);
    }

    public function encryptMasterKey(string $masterKey): EncryptedPayload
    {
        return $this->encryption->encrypt($masterKey, $this->deriveAppKey());
    }

    public function decryptMasterKey(EncryptedPayload $payload): string
    {
        return $this->encryption->decrypt($payload, $this->deriveAppKey());
    }

    public function generateMasterKey(): string
    {
        return $this->encryption->generateKey();
    }

    private function normalizeAppKey(string $appKey): string
    {
        if (! str_starts_with($appKey, 'base64:')) {
            return $appKey;
        }

        $decoded = base64_decode(substr($appKey, 7), true);

        if ($decoded === false || $decoded === '') {
            throw new InvalidKeyException('APP_KEY is base64 encoded but invalid.');
        }

        return $decoded;
    }
}
