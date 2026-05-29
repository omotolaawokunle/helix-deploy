<?php

declare(strict_types=1);

namespace App\Packages\Encryption;

use App\Packages\Encryption\Contracts\EncryptionInterface;
use App\Packages\Encryption\Exceptions\DecryptionFailedException;
use App\Packages\Encryption\Exceptions\InvalidKeyException;

class SodiumEncryption implements EncryptionInterface
{
    public function __construct(
        private readonly KeyGenerator $keyGenerator,
    ) {
    }

    public function encrypt(string $plaintext, string $key): EncryptedPayload
    {
        $keyBytes = $this->decodeAndValidateKey($key);

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $keyBytes);
        } finally {
            $this->wipeKey($keyBytes);
        }

        return new EncryptedPayload(
            ciphertext: base64_encode($ciphertext),
            nonce: base64_encode($nonce),
        );
    }

    public function decrypt(EncryptedPayload $payload, string $key): string
    {
        $keyBytes = $this->decodeAndValidateKey($key);

        try {
            $ciphertext = $this->decodePayloadPart($payload->ciphertext, 'ciphertext');
            $nonce = $this->decodePayloadPart($payload->nonce, 'nonce');

            $result = sodium_crypto_secretbox_open($ciphertext, $nonce, $keyBytes);
        } finally {
            $this->wipeKey($keyBytes);
        }

        if ($result === false) {
            throw new DecryptionFailedException('Unable to decrypt payload with the provided key.');
        }

        return $result;
    }

    public function generateKey(): string
    {
        return $this->keyGenerator->generate();
    }

    protected function wipeKey(string &$keyBytes): void
    {
        sodium_memzero($keyBytes);
    }

    private function decodeAndValidateKey(string $key): string
    {
        $decodedKey = base64_decode($key, true);

        if ($decodedKey === false) {
            throw InvalidKeyException::forSecretBox(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 0);
        }

        $length = strlen($decodedKey);

        if ($length !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw InvalidKeyException::forSecretBox(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $length);
        }

        return $decodedKey;
    }

    private function decodePayloadPart(string $encodedPart, string $partName): string
    {
        $decodedPart = base64_decode($encodedPart, true);

        if ($decodedPart === false) {
            throw new DecryptionFailedException(
                sprintf('Encrypted payload %s is not valid base64.', $partName),
            );
        }

        return $decodedPart;
    }
}
