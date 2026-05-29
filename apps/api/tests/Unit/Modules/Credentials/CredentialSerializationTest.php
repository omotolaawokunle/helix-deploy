<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Credentials;

use App\Models\User;
use App\Modules\Credentials\Enums\CredentialType;
use App\Modules\Credentials\Models\Credential;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class CredentialSerializationTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $this->capsule = new Capsule($container);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->createSchema();
    }

    public function test_to_array_never_includes_encrypted_value_or_nonce(): void
    {
        $organization = Organization::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Credentials Org',
            'slug' => 'credentials-org',
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);

        $userId = Uuid::uuid4()->toString();
        $this->capsule->table('users')->insert([
            'id' => $userId,
            'current_organization_id' => (string) $organization->getKey(),
            'name' => 'Credential User',
            'email' => 'cred-user@example.test',
            'timezone' => 'UTC',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);

        $credential = Credential::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'organization_id' => (string) $organization->getKey(),
            'credentialable_type' => null,
            'credentialable_id' => null,
            'type' => CredentialType::ENV_VAR,
            'name' => 'APP_SECRET',
            'encrypted_value' => 'encrypted-payload',
            'nonce' => 'nonce-payload',
            'key_fingerprint' => null,
            'created_by' => (string) $user->getKey(),
            'last_used_at' => null,
        ]);

        $data = $credential->toArray();

        self::assertArrayNotHasKey('encrypted_value', $data);
        self::assertArrayNotHasKey('nonce', $data);
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
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('current_organization_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('timezone')->default('UTC');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
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
            $table->string('key_fingerprint')->nullable();
            $table->string('created_by');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }
}
