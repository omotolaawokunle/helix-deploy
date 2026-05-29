<?php

declare(strict_types=1);

namespace Tests\Unit\Packages\Encryption;

use App\Packages\Encryption\Contracts\EncryptionInterface;
use App\Packages\Encryption\EncryptedPayload;
use App\Packages\Encryption\Exceptions\DecryptionFailedException;
use App\Packages\Encryption\Exceptions\InvalidKeyException;
use App\Packages\Encryption\KeyGenerator;
use App\Packages\Encryption\MasterKeyManager;
use App\Packages\Encryption\PackageServiceProvider;
use App\Packages\Encryption\SodiumEncryption;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class EncryptionTest extends TestCase
{
    private readonly EncryptionInterface $encryption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryption = new SodiumEncryption(new KeyGenerator());
    }

    public function test_encrypt_then_decrypt_returns_original_plaintext(): void
    {
        $key = $this->encryption->generateKey();
        $plaintext = 'super-secret-value';

        $payload = $this->encryption->encrypt($plaintext, $key);
        $decrypted = $this->encryption->decrypt($payload, $key);

        self::assertSame($plaintext, $decrypted);
    }

    public function test_same_plaintext_encryptions_produce_different_outputs(): void
    {
        $key = $this->encryption->generateKey();
        $plaintext = 'same-plaintext';

        $firstPayload = $this->encryption->encrypt($plaintext, $key);
        $secondPayload = $this->encryption->encrypt($plaintext, $key);

        self::assertNotSame($firstPayload->nonce, $secondPayload->nonce);
        self::assertNotSame($firstPayload->ciphertext, $secondPayload->ciphertext);
    }

    public function test_decrypt_with_wrong_key_throws_exception(): void
    {
        $plaintext = 'confidential';
        $correctKey = $this->encryption->generateKey();
        $wrongKey = $this->encryption->generateKey();
        $payload = $this->encryption->encrypt($plaintext, $correctKey);

        $this->expectException(DecryptionFailedException::class);

        $this->encryption->decrypt($payload, $wrongKey);
    }

    public function test_wrong_key_length_throws_invalid_key_exception(): void
    {
        $invalidKey = base64_encode(random_bytes(16));

        $this->expectException(InvalidKeyException::class);

        $this->encryption->encrypt('value', $invalidKey);
    }

    public function test_sodium_memzero_wipes_key_material_after_use(): void
    {
        $encryption = new class(new KeyGenerator()) extends SodiumEncryption
        {
            public string $lastKeyBeforeWipe = '';

            public string|null $lastKeyAfterWipe = null;

            protected function wipeKey(string &$keyBytes): void
            {
                $this->lastKeyBeforeWipe = $keyBytes;

                parent::wipeKey($keyBytes);

                $this->lastKeyAfterWipe = $keyBytes;
            }
        };

        $key = $encryption->generateKey();
        $payload = $encryption->encrypt('wipe-me', $key);
        $encryption->decrypt($payload, $key);

        self::assertNotSame('', $encryption->lastKeyBeforeWipe);
        self::assertContains($encryption->lastKeyAfterWipe, ['', null]);
    }

    public function test_generate_key_returns_expected_secretbox_length(): void
    {
        $key = $this->encryption->generateKey();
        $decodedKey = base64_decode($key, true);

        self::assertNotFalse($decodedKey);
        self::assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decodedKey));
    }

    public function test_encrypted_payload_round_trips_through_json(): void
    {
        $payload = new EncryptedPayload(
            ciphertext: base64_encode('cipher'),
            nonce: base64_encode('nonce'),
        );

        $roundTrippedPayload = EncryptedPayload::fromJson($payload->toJson());

        self::assertSame($payload->toArray(), $roundTrippedPayload->toArray());
    }

    public function test_master_key_manager_encrypts_and_decrypts_master_key(): void
    {
        $manager = new MasterKeyManager(
            encryption: $this->encryption,
            appKey: 'base64:'.base64_encode(random_bytes(32)),
        );

        $derivedAppKey = $manager->deriveAppKey();
        $decodedDerivedKey = base64_decode($derivedAppKey, true);
        $masterKey = $manager->generateMasterKey();
        $encryptedMasterKey = $manager->encryptMasterKey($masterKey);
        $decryptedMasterKey = $manager->decryptMasterKey($encryptedMasterKey);

        self::assertNotFalse($decodedDerivedKey);
        self::assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decodedDerivedKey));
        self::assertSame($masterKey, $decryptedMasterKey);
    }

    public function test_package_service_provider_binds_singletons(): void
    {
        $container = new Container();
        $provider = new PackageServiceProvider($container);
        $provider->register();

        $firstEncryption = $container->make(EncryptionInterface::class);
        $secondEncryption = $container->make(EncryptionInterface::class);
        $firstManager = $container->make(MasterKeyManager::class);
        $secondManager = $container->make(MasterKeyManager::class);

        self::assertInstanceOf(SodiumEncryption::class, $firstEncryption);
        self::assertSame($firstEncryption, $secondEncryption);
        self::assertInstanceOf(MasterKeyManager::class, $firstManager);
        self::assertSame($firstManager, $secondManager);
    }
}
