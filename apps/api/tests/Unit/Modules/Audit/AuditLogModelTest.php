<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class AuditLogModelTest extends TestCase
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

        $request = Request::create('/audit', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'PHPUnit',
            'HTTP_X_REQUEST_ID' => (string) Uuid::uuid4(),
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $container->instance('request', $request);

        $container->instance('auth', new class {
            public ?User $user = null;

            public function user(): ?User
            {
                return $this->user;
            }

            public function id(): ?string
            {
                return $this->user?->id;
            }

            public function setUser(User $user): void
            {
                $this->user = $user;
            }
        });
    }

    public function test_save_on_existing_record_throws_logic_exception(): void
    {
        $organization = Organization::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Acme',
            'slug' => 'acme',
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);

        $audit = AuditLog::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'operation' => 'deploy.started',
            'created_at' => now(),
        ]);

        $audit->operation = 'deploy.finished';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AuditLog records are immutable');
        $audit->save();
    }

    public function test_delete_throws_logic_exception(): void
    {
        $organization = Organization::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Acme',
            'slug' => 'acme',
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);

        $audit = AuditLog::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'operation' => 'deploy.started',
            'created_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('AuditLog records cannot be deleted');
        $audit->delete();
    }

    public function test_record_captures_correct_organization_and_actor(): void
    {
        $organization = Organization::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Primary',
            'slug' => 'primary',
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);

        $userId = Uuid::uuid4()->toString();
        $this->capsule->table('users')->insert([
            'id' => $userId,
            'current_organization_id' => (string) $organization->getKey(),
            'name' => 'Alice',
            'email' => 'alice@example.test',
            'timezone' => 'UTC',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);

        Auth::setUser($user);

        $record = AuditLog::record('credentials.accessed');

        self::assertSame((string) $organization->getKey(), (string) $record->organization_id);
        self::assertSame((string) $user->getKey(), (string) $record->actor_id);
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

        $schema->create('audit_logs', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id')->nullable();
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
}
