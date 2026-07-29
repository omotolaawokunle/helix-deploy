<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Sites\Models\Site;
use App\Packages\Encryption\EncryptedPayload;
use App\Packages\Encryption\SodiumEncryption;
use InvalidArgumentException;

final class SiteWebhookSecretService
{
    public function __construct(
        private readonly SodiumEncryption $encryption,
        private readonly CredentialVaultInterface $credentialVault,
    ) {
    }

    public function generatePlaintextSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function generateWebhookToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function encryptAndStore(Site $site, Organization $organization, string $plaintextSecret): void
    {
        $masterKey = $this->credentialVault->getMasterKey($organization);

        try {
            $payload = $this->encryption->encrypt($plaintextSecret, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        $site->forceFill([
            'webhook_secret_encrypted' => $payload->ciphertext,
            'webhook_secret_nonce' => $payload->nonce,
        ])->save();
    }

    public function decrypt(Site $site, Organization $organization): string
    {
        if ($site->webhook_secret_encrypted === null || $site->webhook_secret_nonce === null) {
            throw new InvalidArgumentException('Site does not have a webhook secret configured.');
        }

        $masterKey = $this->credentialVault->getMasterKey($organization);

        try {
            $plaintext = $this->encryption->decrypt(
                new EncryptedPayload(
                    ciphertext: $site->webhook_secret_encrypted,
                    nonce: $site->webhook_secret_nonce,
                ),
                $masterKey,
            );
        } finally {
            sodium_memzero($masterKey);
        }

        return $plaintext;
    }

    public function hasSecret(Site $site): bool
    {
        return $site->webhook_secret_encrypted !== null
            && $site->webhook_secret_nonce !== null;
    }
}
