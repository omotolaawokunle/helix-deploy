<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Credentials;

use App\Models\Organization;
use App\Modules\Credentials\CredentialVault;
use App\Modules\Credentials\Exceptions\CredentialAccessDeniedException;
use App\Modules\Credentials\Models\Credential;
use App\Packages\Encryption\KeyGenerator;
use App\Packages\Encryption\MasterKeyManager;
use App\Packages\Encryption\SodiumEncryption;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class CredentialVaultTest extends TestCase
{
    private Capsule $capsule;

    private CredentialVault $vault;

    private Organization $organization;

    private Organization $otherOrganization;

    private FakeCredentialOwner $owner;

    private string $masterKey;

    private string $appKey;

    private MasterKeyManager $masterKeyManager;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        Container::setInstance($container);

        $this->capsule = new Capsule($container);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->createSchema();

        $this->appKey = 'base64:'.base64_encode(random_bytes(32));

        $encryption = new SodiumEncryption(new KeyGenerator());
        $this->masterKeyManager = new MasterKeyManager($encryption, $this->appKey);
        $this->vault = new CredentialVault($encryption, $this->masterKeyManager);

        $this->seedFixtures($this->masterKeyManager);
    }

    public function test_generate_ssh_key_pair_stores_encrypted_private_key(): void
    {
        $storedPair = $this->vault->generateSSHKeyPair($this->organization, $this->owner, 'deploy-key');
        $credential = Credential::query()->findOrFail($storedPair->credentialId);

        self::assertStringStartsWith('ssh-ed25519 ', $storedPair->publicKey);
        self::assertNotSame($storedPair->publicKey, $credential->encrypted_value);
        self::assertSame($storedPair->publicKey, $credential->key_fingerprint);
    }

    public function test_get_private_key_returns_plaintext_and_writes_audit_and_last_used(): void
    {
        $credential = $this->vault->storePrivateKey(
            organization: $this->organization,
            owner: $this->owner,
            name: 'manual-key',
            key: 'PRIVATE_KEY_MATERIAL',
        );

        $plaintext = $this->vault->getPrivateKey((string) $credential->getKey(), $this->organization);
        $credential->refresh();

        self::assertSame('PRIVATE_KEY_MATERIAL', $plaintext);
        self::assertNotNull($credential->last_used_at);
        self::assertDatabaseHasAudit('credential.accessed', (string) $credential->getKey());
    }

    public function test_get_private_key_with_wrong_org_throws(): void
    {
        $credential = $this->vault->storePrivateKey(
            organization: $this->organization,
            owner: $this->owner,
            name: 'manual-key',
            key: 'PRIVATE_KEY_MATERIAL',
        );

        $this->expectException(CredentialAccessDeniedException::class);
        $this->vault->getPrivateKey((string) $credential->getKey(), $this->otherOrganization);
    }

    public function test_rotate_replaces_private_key_material_and_public_key_changes(): void
    {
        $originalPair = $this->vault->generateSSHKeyPair($this->organization, $this->owner, 'rotating-key');
        $originalPrivateKey = $this->vault->getPrivateKey($originalPair->credentialId, $this->organization);

        $rotatedPair = $this->vault->rotate($originalPair->credentialId, $this->organization);
        $rotatedPrivateKey = $this->vault->getPrivateKey($rotatedPair->credentialId, $this->organization);

        self::assertNotSame($originalPair->publicKey, $rotatedPair->publicKey);
        self::assertNotSame($originalPrivateKey, $rotatedPrivateKey);
        self::assertDatabaseHasAudit('credential.rotated', $originalPair->credentialId);
    }

    public function test_delete_hard_deletes_credential_and_writes_audit_log(): void
    {
        $credential = $this->vault->storeSecret(
            organization: $this->organization,
            owner: $this->owner,
            name: 'api-token',
            value: 'SECRET_VALUE',
        );

        $id = (string) $credential->getKey();
        $this->vault->delete($id, $this->organization);

        self::assertNull(Credential::query()->find($id));
        self::assertDatabaseHasAudit('credential.deleted', $id);
    }

    public function test_credential_model_hides_encrypted_fields_in_array_and_json(): void
    {
        $credential = $this->vault->storeSecret(
            organization: $this->organization,
            owner: $this->owner,
            name: 'api-token',
            value: 'SECRET_VALUE',
        );

        $asArray = $credential->toArray();
        $asJson = $credential->toJson();

        self::assertArrayNotHasKey('encrypted_value', $asArray);
        self::assertArrayNotHasKey('nonce', $asArray);
        self::assertStringNotContainsString('encrypted_value', $asJson);
        self::assertStringNotContainsString('nonce', $asJson);
    }

    public function test_rekey_organization_keeps_credentials_readable_with_new_master_key(): void
    {
        $credential = $this->vault->storeSecret(
            organization: $this->organization,
            owner: $this->owner,
            name: 'api-token',
            value: 'SECRET_VALUE',
        );

        $oldMasterKey = $this->masterKey;
        $newMasterKey = $this->masterKeyManager->generateMasterKey();

        $this->organization->master_key_encrypted = (new MasterKeyManager(new SodiumEncryption(new KeyGenerator()), $this->appKey))
            ->encryptMasterKey($newMasterKey)
            ->toJson();
        $this->organization->save();

        $this->vault->rekeyOrganization($this->organization, $oldMasterKey);
        $plaintext = $this->vault->getSecret((string) $credential->getKey(), $this->organization);

        self::assertSame('SECRET_VALUE', $plaintext);
        self::assertDatabaseHasAudit('organization.rekeyed', (string) $this->organization->getKey());
    }

    private function createSchema(): void
    {
        $schema = $this->capsule->schema();

        $schema->create('organizations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->text('master_key_encrypted');
            $table->text('settings')->nullable();
            $table->string('owner_id');
            $table->timestamps();
        });

        $schema->create('fake_owners', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('credentials', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('credentialable_type')->nullable();
            $table->string('credentialable_id')->nullable();
            $table->string('type');
            $table->string('name');
            $table->text('encrypted_value');
            $table->text('nonce');
            $table->text('key_fingerprint')->nullable();
            $table->string('created_by');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        $schema->create('audit_logs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('actor_id')->nullable();
            $table->string('operation');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->text('before_state')->nullable();
            $table->text('after_state')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    private function seedFixtures(MasterKeyManager $masterKeyManager): void
    {
        $this->masterKey = $masterKeyManager->generateMasterKey();
        $encryptedMasterKey = $masterKeyManager->encryptMasterKey($this->masterKey)->toJson();

        $organizationId = Uuid::uuid4()->toString();
        $otherOrganizationId = Uuid::uuid4()->toString();
        $ownerId = Uuid::uuid4()->toString();
        $otherOwnerId = Uuid::uuid4()->toString();

        $this->capsule->table('organizations')->insert([
            [
                'id' => $organizationId,
                'name' => 'Primary Org',
                'slug' => 'primary-org',
                'master_key_encrypted' => $encryptedMasterKey,
                'settings' => '{}',
                'owner_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $otherOrganizationId,
                'name' => 'Other Org',
                'slug' => 'other-org',
                'master_key_encrypted' => $encryptedMasterKey,
                'settings' => '{}',
                'owner_id' => $otherOwnerId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->capsule->table('fake_owners')->insert([
            'id' => $ownerId,
            'name' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->organization = Organization::query()->findOrFail($organizationId);
        $this->otherOrganization = Organization::query()->findOrFail($otherOrganizationId);
        $this->owner = FakeCredentialOwner::query()->findOrFail($ownerId);
    }

    private static function assertDatabaseHasAudit(string $operation, string $resourceId): void
    {
        $exists = \App\Modules\Audit\Models\AuditLog::query()
            ->where('operation', $operation)
            ->where('resource_id', $resourceId)
            ->exists();

        self::assertTrue($exists);
    }
}

class FakeCredentialOwner extends Model
{
    protected $table = 'fake_owners';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
