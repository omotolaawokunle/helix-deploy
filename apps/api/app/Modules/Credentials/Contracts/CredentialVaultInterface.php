<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Contracts;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Credentials\DTOs\StoredKeyPair;
use App\Modules\Credentials\Models\Credential;
use Illuminate\Database\Eloquent\Model;

interface CredentialVaultInterface
{
    public function generateSSHKeyPair(Organization $organization, Model $owner, string $name): StoredKeyPair;

    public function storePrivateKey(Organization $organization, Model $owner, string $name, string $key): Credential;

    public function storeSecret(Organization $organization, Model $owner, string $name, string $value): Credential;

    public function updateSecret(string $credentialId, Organization $organization, string $value): Credential;

    public function getPrivateKey(string $credentialId, Organization $organization): string;

    public function getSecret(string $credentialId, Organization $organization): string;

    public function getPublicKey(string $credentialId, Organization $organization): string;

    public function rotate(string $credentialId, Organization $organization): StoredKeyPair;

    public function delete(string $credentialId, Organization $organization): void;

    public function rekeyOrganization(Organization $organization, string $oldMasterKey): void;
}
