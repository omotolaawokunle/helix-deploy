<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Shared;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Projects\Models\Project;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class OwnedByOrganizationScopeTest extends TestCase
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

    public function test_scoped_model_query_never_returns_other_org_records(): void
    {
        $firstOrg = Organization::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Org One',
            'slug' => 'org-one',
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);

        $secondOrg = Organization::query()->create([
            'id' => Uuid::uuid4()->toString(),
            'name' => 'Org Two',
            'slug' => 'org-two',
            'master_key_encrypted' => '{}',
            'settings' => [],
        ]);

        $userId = Uuid::uuid4()->toString();
        $this->capsule->table('users')->insert([
            'id' => $userId,
            'current_organization_id' => (string) $firstOrg->getKey(),
            'name' => 'Scoped User',
            'email' => 'scoped@example.test',
            'timezone' => 'UTC',
            'password' => 'password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);

        Auth::setUser($user);

        Project::query()->withoutGlobalScope('owned_by_organization')->create([
            'id' => Uuid::uuid4()->toString(),
            'organization_id' => (string) $firstOrg->getKey(),
            'name' => 'Primary Project',
            'description' => 'belongs to current org',
        ]);

        Project::query()->withoutGlobalScope('owned_by_organization')->create([
            'id' => Uuid::uuid4()->toString(),
            'organization_id' => (string) $secondOrg->getKey(),
            'name' => 'Foreign Project',
            'description' => 'belongs to other org',
        ]);

        $projectNames = Project::query()->pluck('name')->all();

        self::assertSame(['Primary Project'], $projectNames);
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

        $schema->create('projects', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
}
