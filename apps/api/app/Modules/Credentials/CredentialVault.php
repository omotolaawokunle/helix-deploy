<?php

declare(strict_types=1);

namespace App\Modules\Credentials;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Credentials\DTOs\StoredKeyPair;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Exceptions\CredentialAccessDeniedException;
use App\Modules\Credentials\Exceptions\CredentialNotFoundException;
use App\Modules\Credentials\Models\Credential;
use App\Packages\Encryption\EncryptedPayload;
use App\Packages\Encryption\MasterKeyManager;
use App\Packages\Encryption\SodiumEncryption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use phpseclib3\Crypt\EC;

class CredentialVault implements CredentialVaultInterface
{
    public function __construct(
        private readonly SodiumEncryption $encryption,
        private readonly MasterKeyManager $masterKeyManager,
    ) {
    }

    public function generateSSHKeyPair(Organization $organization, Model $owner, string $name): StoredKeyPair
    {
        $key = EC::createKey('Ed25519');
        $privateKey = $key->toString('OpenSSH', ['password' => '']);
        $publicKey = $key->getPublicKey()->toString('OpenSSH');

        $credential = $this->storeEncrypted(
            organization: $organization,
            owner: $owner,
            name: $name,
            type: CredentialType::SSH_PRIVATE_KEY,
            plaintext: $privateKey,
            publicKey: $publicKey,
        );

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.created',
            resourceId: (string) $credential->getKey(),
            afterState: [
                'name' => $credential->name,
                'type' => $credential->type?->value,
            ],
        );

        return new StoredKeyPair(
            credentialId: (string) $credential->getKey(),
            publicKey: $publicKey,
        );
    }

    public function storePrivateKey(Organization $organization, Model $owner, string $name, string $key): Credential
    {
        $credential = $this->storeEncrypted(
            organization: $organization,
            owner: $owner,
            name: $name,
            type: CredentialType::SSH_PRIVATE_KEY,
            plaintext: $key,
            publicKey: null,
        );

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.created',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $credential;
    }

    public function storeSecret(Organization $organization, Model $owner, string $name, string $value): Credential
    {
        $credential = $this->storeEncrypted(
            organization: $organization,
            owner: $owner,
            name: $name,
            type: CredentialType::ENV_VAR,
            plaintext: $value,
            publicKey: null,
        );

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.created',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $credential;
    }

    public function storeGitProviderToken(Organization $organization, string $name, string $token): Credential
    {
        $existing = Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::GIT_PROVIDER_TOKEN)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $this->updateGitProviderToken((string) $existing->getKey(), $organization, $token);
        }

        $credential = $this->storeEncrypted(
            organization: $organization,
            owner: $organization,
            name: $name,
            type: CredentialType::GIT_PROVIDER_TOKEN,
            plaintext: $token,
            publicKey: null,
        );

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.created',
            resourceId: (string) $credential->getKey(),
            afterState: [
                'name' => $credential->name,
                'type' => $credential->type?->value,
            ],
        );

        return $credential;
    }

    public function updateGitProviderToken(string $credentialId, Organization $organization, string $token): Credential
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::GIT_PROVIDER_TOKEN);

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $masterKey = $this->getMasterKey($organization);

        try {
            $payload = $this->encryption->encrypt($token, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        $credential->forceFill([
            'encrypted_value' => $payload->ciphertext,
            'nonce' => $payload->nonce,
            'last_used_at' => null,
        ])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.updated',
            resourceId: (string) $credential->getKey(),
            beforeState: $beforeState,
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $credential;
    }

    public function getGitProviderToken(Organization $organization, string $name): string
    {
        $credential = $this->findGitProviderCredential($organization, $name);

        if ($credential === null) {
            throw new CredentialNotFoundException('Git provider credential not found.');
        }

        $plaintext = $this->decryptCredential($organization, $credential);

        $credential->forceFill(['last_used_at' => now()])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.accessed',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $plaintext;
    }

    public function findGitProviderCredential(Organization $organization, string $name): ?Credential
    {
        return Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::GIT_PROVIDER_TOKEN)
            ->where('name', $name)
            ->first();
    }

    public function storeCloudProviderCredential(Organization $organization, string $name, string $payload): Credential
    {
        $existing = Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::CLOUD_PROVIDER_CREDENTIAL)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $this->updateCloudProviderCredential((string) $existing->getKey(), $organization, $payload);
        }

        $credential = $this->storeEncrypted(
            organization: $organization,
            owner: $organization,
            name: $name,
            type: CredentialType::CLOUD_PROVIDER_CREDENTIAL,
            plaintext: $payload,
            publicKey: null,
        );

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.created',
            resourceId: (string) $credential->getKey(),
            afterState: [
                'name' => $credential->name,
                'type' => $credential->type?->value,
            ],
        );

        return $credential;
    }

    public function updateCloudProviderCredential(string $credentialId, Organization $organization, string $payload): Credential
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::CLOUD_PROVIDER_CREDENTIAL);

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $masterKey = $this->getMasterKey($organization);

        try {
            $encrypted = $this->encryption->encrypt($payload, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        $credential->forceFill([
            'encrypted_value' => $encrypted->ciphertext,
            'nonce' => $encrypted->nonce,
            'last_used_at' => null,
        ])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.updated',
            resourceId: (string) $credential->getKey(),
            beforeState: $beforeState,
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $credential;
    }

    public function getCloudProviderCredential(Organization $organization, string $name): string
    {
        $credential = $this->findCloudProviderCredential($organization, $name);

        if ($credential === null) {
            throw new CredentialNotFoundException('Cloud provider credential not found.');
        }

        $plaintext = $this->decryptCredential($organization, $credential);

        $credential->forceFill(['last_used_at' => now()])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.accessed',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $plaintext;
    }

    public function findCloudProviderCredential(Organization $organization, string $name): ?Credential
    {
        return Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::CLOUD_PROVIDER_CREDENTIAL)
            ->where('name', $name)
            ->first();
    }

    public function deleteCloudProviderCredential(Organization $organization, string $name): void
    {
        $credential = $this->findCloudProviderCredential($organization, $name);

        if ($credential === null) {
            return;
        }

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $credential->delete();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.deleted',
            resourceId: (string) $credential->getKey(),
            beforeState: $beforeState,
        );
    }

    public function storeDnsProviderCredential(Organization $organization, string $name, string $payload): Credential
    {
        $existing = Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::DNS_PROVIDER_CREDENTIAL)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $this->updateDnsProviderCredential((string) $existing->getKey(), $organization, $payload);
        }

        $credential = $this->storeEncrypted(
            organization: $organization,
            owner: $organization,
            name: $name,
            type: CredentialType::DNS_PROVIDER_CREDENTIAL,
            plaintext: $payload,
            publicKey: null,
        );

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.created',
            resourceId: (string) $credential->getKey(),
            afterState: [
                'name' => $credential->name,
                'type' => $credential->type?->value,
            ],
        );

        return $credential;
    }

    public function updateDnsProviderCredential(string $credentialId, Organization $organization, string $payload): Credential
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::DNS_PROVIDER_CREDENTIAL);

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $masterKey = $this->getMasterKey($organization);

        try {
            $encrypted = $this->encryption->encrypt($payload, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        $credential->forceFill([
            'encrypted_value' => $encrypted->ciphertext,
            'nonce' => $encrypted->nonce,
            'last_used_at' => null,
        ])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.updated',
            resourceId: (string) $credential->getKey(),
            beforeState: $beforeState,
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $credential;
    }

    public function getDnsProviderCredential(Organization $organization, string $name): string
    {
        $credential = $this->findDnsProviderCredential($organization, $name);

        if ($credential === null) {
            throw new CredentialNotFoundException('DNS provider credential not found.');
        }

        $plaintext = $this->decryptCredential($organization, $credential);

        $credential->forceFill(['last_used_at' => now()])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.accessed',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $plaintext;
    }

    public function findDnsProviderCredential(Organization $organization, string $name): ?Credential
    {
        return Credential::query()
            ->forOrganization($organization)
            ->ofType(CredentialType::DNS_PROVIDER_CREDENTIAL)
            ->where('name', $name)
            ->first();
    }

    public function deleteDnsProviderCredential(Organization $organization, string $name): void
    {
        $credential = $this->findDnsProviderCredential($organization, $name);

        if ($credential === null) {
            return;
        }

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $credential->delete();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.deleted',
            resourceId: (string) $credential->getKey(),
            beforeState: $beforeState,
        );
    }

    public function updateSecret(string $credentialId, Organization $organization, string $value): Credential
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::ENV_VAR);

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $masterKey = $this->getMasterKey($organization);

        try {
            $payload = $this->encryption->encrypt($value, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        $credential->forceFill([
            'encrypted_value' => $payload->ciphertext,
            'nonce' => $payload->nonce,
            'last_used_at' => null,
        ])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.updated',
            resourceId: (string) $credential->getKey(),
            beforeState: $beforeState,
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $credential;
    }

    public function getPrivateKey(string $credentialId, Organization $organization): string
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::SSH_PRIVATE_KEY);

        $plaintext = $this->decryptCredential($organization, $credential);

        $credential->forceFill(['last_used_at' => now()])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.accessed',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $plaintext;
    }

    public function getSecret(string $credentialId, Organization $organization): string
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::ENV_VAR);

        $plaintext = $this->decryptCredential($organization, $credential);

        $credential->forceFill(['last_used_at' => now()])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.accessed',
            resourceId: (string) $credential->getKey(),
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value],
        );

        return $plaintext;
    }

    public function getPublicKey(string $credentialId, Organization $organization): string
    {
        $credential = $this->loadCredential($credentialId, $organization);

        return (string) $credential->key_fingerprint;
    }

    public function rotate(string $credentialId, Organization $organization): StoredKeyPair
    {
        $credential = $this->loadCredential($credentialId, $organization);
        $this->assertCredentialType($credential, CredentialType::SSH_PRIVATE_KEY);

        $oldState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
            'key_fingerprint' => $credential->key_fingerprint,
        ];

        $key = EC::createKey('Ed25519');
        $privateKey = $key->toString('OpenSSH', ['password' => '']);
        $publicKey = $key->getPublicKey()->toString('OpenSSH');

        $masterKey = $this->getMasterKey($organization);

        try {
            $payload = $this->encryption->encrypt($privateKey, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        $credential->forceFill([
            'encrypted_value' => $payload->ciphertext,
            'nonce' => $payload->nonce,
            'key_fingerprint' => $publicKey,
            'last_used_at' => null,
        ])->save();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.rotated',
            resourceId: (string) $credential->getKey(),
            beforeState: $oldState,
            afterState: ['name' => $credential->name, 'type' => $credential->type?->value, 'key_fingerprint' => $publicKey],
        );

        return new StoredKeyPair(
            credentialId: (string) $credential->getKey(),
            publicKey: $publicKey,
        );
    }

    public function delete(string $credentialId, Organization $organization): void
    {
        $credential = $this->loadCredential($credentialId, $organization);

        $beforeState = [
            'name' => $credential->name,
            'type' => $credential->type?->value,
        ];

        $credential->delete();

        $this->writeAuditLog(
            organization: $organization,
            operation: 'credential.deleted',
            resourceId: $credentialId,
            beforeState: $beforeState,
        );
    }

    public function rekeyOrganization(Organization $organization, string $oldMasterKey): void
    {
        $newMasterKey = $this->getMasterKey($organization);

        try {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Credential> $credentials */
            $credentials = Credential::query()->forOrganization($organization)->get();

            foreach ($credentials as $credential) {
                $oldPayload = new EncryptedPayload(
                    ciphertext: (string) $credential->encrypted_value,
                    nonce: (string) $credential->nonce,
                );

                $plaintext = $this->encryption->decrypt($oldPayload, $oldMasterKey);

                try {
                    $newPayload = $this->encryption->encrypt($plaintext, $newMasterKey);
                } finally {
                    sodium_memzero($plaintext);
                }

                $credential->forceFill([
                    'encrypted_value' => $newPayload->ciphertext,
                    'nonce' => $newPayload->nonce,
                ])->save();
            }
        } finally {
            sodium_memzero($newMasterKey);
        }

        $this->writeAuditLog(
            organization: $organization,
            operation: 'organization.rekeyed',
            resourceId: (string) $organization->getKey(),
            afterState: ['credentials_count' => Credential::query()->forOrganization($organization)->count()],
        );
    }

    public function getMasterKey(Organization $organization): string
    {
        $encryptedMasterKey = EncryptedPayload::fromJson((string) $organization->master_key_encrypted);

        return $this->masterKeyManager->decryptMasterKey($encryptedMasterKey);
    }

    private function resolveCreatorId(Organization $organization): string
    {
        if (Auth::getFacadeRoot() !== null) {
            $authId = Auth::id();

            if ($authId !== null) {
                return (string) $authId;
            }
        }

        $ownerId = $organization->getAttribute('owner_id');

        if (is_string($ownerId) && $ownerId !== '') {
            return $ownerId;
        }

        $memberId = $organization->users()->value('users.id');

        if ($memberId !== null) {
            return (string) $memberId;
        }

        throw new \RuntimeException('Unable to resolve credential creator.');
    }

    private function storeEncrypted(
        Organization $organization,
        Model $owner,
        string $name,
        CredentialType $type,
        string $plaintext,
        string|null $publicKey,
    ): Credential {
        $masterKey = $this->getMasterKey($organization);

        try {
            $payload = $this->encryption->encrypt($plaintext, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }

        return Credential::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'credentialable_type' => $owner->getMorphClass(),
            'credentialable_id' => (string) $owner->getKey(),
            'type' => $type->value,
            'name' => $name,
            'encrypted_value' => $payload->ciphertext,
            'nonce' => $payload->nonce,
            'key_fingerprint' => $publicKey,
            'created_by' => $this->resolveCreatorId($organization),
            'last_used_at' => null,
        ]);
    }

    private function decryptCredential(Organization $organization, Credential $credential): string
    {
        $masterKey = $this->getMasterKey($organization);

        try {
            $payload = new EncryptedPayload(
                ciphertext: (string) $credential->encrypted_value,
                nonce: (string) $credential->nonce,
            );

            return $this->encryption->decrypt($payload, $masterKey);
        } finally {
            sodium_memzero($masterKey);
        }
    }

    private function loadCredential(string $credentialId, Organization $organization): Credential
    {
        $credential = Credential::query()->find($credentialId);

        if ($credential === null) {
            throw new CredentialNotFoundException('Credential not found.');
        }

        if ((string) $credential->organization_id !== (string) $organization->getKey()) {
            throw new CredentialAccessDeniedException('Credential access denied for this organization.');
        }

        return $credential;
    }

    private function assertCredentialType(Credential $credential, CredentialType $expectedType): void
    {
        if ($credential->type !== $expectedType) {
            throw new CredentialAccessDeniedException('Credential type does not match requested operation.');
        }
    }

    /**
     * @param array<string, mixed>|null $beforeState
     * @param array<string, mixed>|null $afterState
     */
    private function writeAuditLog(
        Organization $organization,
        string $operation,
        string|null $resourceId,
        array|null $beforeState = null,
        array|null $afterState = null,
    ): void {
        AuditLog::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'actor_id' => null,
            'operation' => $operation,
            'resource_type' => Credential::class,
            'resource_id' => $resourceId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'ip_address' => null,
            'user_agent' => null,
            'request_id' => null,
            'created_at' => now(),
        ]);
    }
}
