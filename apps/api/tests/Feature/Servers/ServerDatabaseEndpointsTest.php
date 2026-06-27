<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Credentials\Contracts\CredentialVaultInterface;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Servers\Jobs\BrowseServerDatabaseJob;
use App\Modules\Servers\Models\Server;
use App\Modules\Sites\Enums\DeployMode;
use App\Modules\Sites\Enums\Runtime;
use App\Modules\Sites\Enums\SiteStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Teams\Enums\TeamRole;
use App\Packages\DatabaseBrowser\ReadOnlyDatabaseClient;
use App\Packages\SSH\FakeSSHConnection;
use App\Packages\SSH\SSHResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('accepts refresh=true as a query string for database browse', function (): void {
    Queue::fake();

    [$server, $owner] = serverDatabaseApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases?engine=postgresql&refresh=true")
        ->assertOk();
});

it('queues server database browse and returns loading status', function (): void {
    Queue::fake();

    [$server, $owner] = serverDatabaseApiFixture();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeServerSecret(
        $server->organization,
        $server,
        'db-browser.test-postgresql-deploy-password',
        'postgres-secret',
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases?engine=postgresql")
        ->assertOk()
        ->assertJsonPath('data.status', 'loading')
        ->assertJsonPath('data.kind', 'databases');

    Queue::assertPushed(BrowseServerDatabaseJob::class);
});

it('lists server databases from cache after job completes', function (): void {
    [$server, $owner] = serverDatabaseApiFixture();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeServerSecret(
        $server->organization,
        $server,
        'db-browser.test-postgresql-deploy-password',
        'postgres-secret',
    );

    $cacheKey = BrowseServerDatabaseJob::cacheKey(
        (string) $server->getKey(),
        \App\Packages\DatabaseBrowser\Enums\DatabaseEngine::POSTGRESQL,
        \App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind::DATABASES,
        null,
        null,
        null,
    );

    Cache::put($cacheKey, [
        'status' => 'ready',
        'databases' => ['app_production', 'postgres'],
    ], now()->addMinutes(5));

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases?engine=postgresql")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonCount(2, 'data.databases');
});

it('rejects invalid table names for row browse', function (): void {
    [$server, $owner] = serverDatabaseApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases/app/tables/users;drop/rows?engine=postgresql")
        ->assertUnprocessable();
});

it('writes audit log when browsing table rows', function (): void {
    Queue::fake();

    [$server, $owner] = serverDatabaseApiFixture();

    $vault = app(CredentialVaultInterface::class);
    $vault->storeServerSecret(
        $server->organization,
        $server,
        'db-browser.test-postgresql-deploy-password',
        'postgres-secret',
    );

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases/app/tables/users/rows?engine=postgresql")
        ->assertOk();

    expect(AuditLog::query()->where('operation', 'database.browsed')->exists())->toBeTrue();
});

it('rejects row filters without required values', function (): void {
    [$server, $owner] = serverDatabaseApiFixture();

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases/app/tables/users/rows?engine=postgresql&filter[0][column]=email&filter[0][operator]=eq")
        ->assertUnprocessable();
});

it('returns paginated rows from cache with hasMore', function (): void {
    [$server, $owner] = serverDatabaseApiFixture();

    $rowQuery = new \App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery(page: 2, limit: 2);

    $cacheKey = BrowseServerDatabaseJob::cacheKey(
        (string) $server->getKey(),
        \App\Packages\DatabaseBrowser\Enums\DatabaseEngine::POSTGRESQL,
        \App\Packages\DatabaseBrowser\Enums\DatabaseBrowseKind::ROWS,
        'app',
        'users',
        $rowQuery,
    );

    Cache::put($cacheKey, [
        'status' => 'ready',
        'database' => 'app',
        'table' => 'users',
        'columns' => ['id', 'email'],
        'rows' => [['3', 'c@example.com'], ['4', 'd@example.com']],
        'rowCount' => 2,
        'hasMore' => true,
        'page' => 2,
        'limit' => 2,
        'offset' => 2,
        'filters' => [],
    ], now()->addMinutes(5));

    $this->actingAs($owner)
        ->getJson("/api/v1/servers/{$server->id}/databases/app/tables/users/rows?engine=postgresql&page=2&limit=2")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.page', 2)
        ->assertJsonPath('data.hasMore', true)
        ->assertJsonCount(2, 'data.rows');
});

it('read only database client paginates postgresql rows via ssh', function (): void {
    $ssh = new FakeSSHConnection();
    $ssh->addResponse(
        'PGPASSWORD=*',
        new SSHResult('psql', 0, "id\temail\n1\ta@example.com\n2\tb@example.com\n3\tc@example.com\n", '', 0.0),
    );

    $client = app(ReadOnlyDatabaseClient::class);
    $config = new \App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig(
        engine: \App\Packages\DatabaseBrowser\Enums\DatabaseEngine::POSTGRESQL,
        host: '127.0.0.1',
        port: 5432,
        username: 'deploy',
        password: 'secret',
    );

    $result = $client->fetchRows(
        $ssh,
        $config,
        'app',
        'users',
        new \App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery(page: 1, limit: 2),
    );

    expect($result['rows'])->toHaveCount(2)
        ->and($result['hasMore'])->toBeTrue()
        ->and($result['page'])->toBe(1);
});

it('read only database client applies filters in postgresql query', function (): void {
    $ssh = new FakeSSHConnection();
    $ssh->addResponse(
        'PGPASSWORD=*',
        new SSHResult('psql', 0, "id\temail\n1\ta@example.com\n", '', 0.0),
    );

    $client = app(ReadOnlyDatabaseClient::class);
    $config = new \App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig(
        engine: \App\Packages\DatabaseBrowser\Enums\DatabaseEngine::POSTGRESQL,
        host: '127.0.0.1',
        port: 5432,
        username: 'deploy',
        password: 'secret',
    );

    $result = $client->fetchRows(
        $ssh,
        $config,
        'app',
        'users',
        new \App\Packages\DatabaseBrowser\DTOs\DatabaseRowQuery(
            filters: [
                new \App\Packages\DatabaseBrowser\DTOs\DatabaseRowFilter(
                    column: 'email',
                    operator: \App\Packages\DatabaseBrowser\Enums\DatabaseRowFilterOperator::CONTAINS,
                    value: 'example',
                ),
            ],
        ),
    );

    expect($result['rows'])->toHaveCount(1);
    expect($ssh->getExecutedCommands())->not->toBeEmpty();
    expect($ssh->getExecutedCommands()[0])->toContain('WHERE');
    expect($ssh->getExecutedCommands()[0])->toContain('LIKE');
});

it('read only database client strips mysql cli password warnings from table listings', function (): void {
    $ssh = new FakeSSHConnection();
    $ssh->addResponse(
        'MYSQL_PWD=*',
        new SSHResult(
            'mysql',
            0,
            "mysql: [Warning] Using a password on the command line interface can be insecure.\nusers\nposts\n",
            '',
            0.0,
        ),
    );

    $client = app(ReadOnlyDatabaseClient::class);
    $config = new \App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig(
        engine: \App\Packages\DatabaseBrowser\Enums\DatabaseEngine::MYSQL,
        host: '127.0.0.1',
        port: 3306,
        username: 'deploy',
        password: 'secret',
    );

    expect($client->listTables($ssh, $config, 'app'))->toBe(['users', 'posts']);
});

it('read only database client lists postgresql databases via ssh', function (): void {
    $ssh = new FakeSSHConnection();
    $ssh->addResponse('PGPASSWORD=*', new SSHResult('psql', 0, "app_production\npostgres\n", '', 0.0));

    $client = app(ReadOnlyDatabaseClient::class);
    $config = new \App\Packages\DatabaseBrowser\DTOs\DatabaseConnectionConfig(
        engine: \App\Packages\DatabaseBrowser\Enums\DatabaseEngine::POSTGRESQL,
        host: '127.0.0.1',
        port: 5432,
        username: 'deploy',
        password: 'secret',
    );

    expect($client->listDatabases($ssh, $config))->toBe(['app_production', 'postgres']);
});

/**
 * @return array{0: Server, 1: User}
 */
function serverDatabaseApiFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'DB Browser Org',
        'slug' => 'db-browser-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($owner->getKey(), ['role' => TeamRole::OWNER->value]);

    $server = Server::query()->withoutGlobalScope('owned_by_organization')->create([
        'organization_id' => (string) $organization->getKey(),
        'hostname' => 'db-browser.test',
        'ip_address' => '10.0.0.40',
        'ssh_port' => 22,
        'ssh_user' => 'deploy',
        'provider' => 'generic',
        'status' => 'active',
        'management_mode' => 'managed',
        'created_by' => (string) $owner->getKey(),
        'tags' => [],
        'installed_services' => [
            'postgresql' => ['installed' => true, 'status' => 'running'],
        ],
    ]);

    return [$server, $owner];
}
