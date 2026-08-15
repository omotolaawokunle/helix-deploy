<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createGitProviderFixture(TeamRole $role = TeamRole::OWNER): array
{
    $organization = Organization::query()->create([
        'name' => 'Git Provider Org',
        'slug' => 'git-provider-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => $role->value]);

    return [$organization, $user];
}

it('stores git provider tokens lists repositories and never returns token values', function (): void {
    [$organization, $owner] = createGitProviderFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/git-providers", [
            'provider' => 'github',
            'token' => 'ghp_test_secret_token_value',
        ])
        ->assertCreated()
        ->assertJsonPath('data.provider', 'github')
        ->assertJsonMissing(['token'])
        ->assertJsonMissing(['ghp_test_secret_token_value']);

    expect(AuditLog::query()->where('operation', 'credential.created')->exists())->toBeTrue();

    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'id' => 1,
                'name' => 'private-app',
                'full_name' => 'acme/private-app',
                'clone_url' => 'https://github.com/acme/private-app.git',
                'default_branch' => 'main',
                'private' => true,
            ],
        ]),
        'api.github.com/repos/acme/private-app/branches*' => Http::response([
            ['name' => 'main'],
            ['name' => 'develop'],
        ]),
    ]);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/git-providers")
        ->assertOk()
        ->assertJsonFragment(['provider' => 'github']);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/git-providers/github/repositories")
        ->assertOk()
        ->assertJsonPath('data.0.fullName', 'acme/private-app')
        ->assertJsonMissing(['ghp_test_secret_token_value']);

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/git-providers/github/repositories/acme/private-app/branches")
        ->assertOk()
        ->assertJsonFragment(['name' => 'main']);

    expect(AuditLog::query()->where('operation', 'credential.accessed')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->deleteJson("/api/v1/organizations/{$organization->id}/git-providers/github")
        ->assertNoContent();
});

it('forbids developers from storing git provider tokens', function (): void {
    [$organization, $developer] = createGitProviderFixture(TeamRole::DEVELOPER);

    $this->actingAs($developer)
        ->postJson("/api/v1/organizations/{$organization->id}/git-providers", [
            'provider' => 'github',
            'token' => 'ghp_test_secret_token_value',
        ])
        ->assertForbidden();
});

it('lists bitbucket repositories from workspace-scoped endpoints using an api token', function (): void {
    [$organization, $owner] = createGitProviderFixture();

    Http::fake([
        'api.bitbucket.org/2.0/user/workspaces*' => Http::response([
            'values' => [
                [
                    'workspace' => ['slug' => 'acme'],
                ],
            ],
        ]),
        'api.bitbucket.org/2.0/repositories/acme/private-app/refs/branches*' => Http::response([
            'values' => [['name' => 'main']],
        ]),
        'api.bitbucket.org/2.0/repositories/acme*' => Http::response([
            'values' => [
                [
                    'name' => 'private-app',
                    'full_name' => 'acme/private-app',
                    'is_private' => true,
                    'mainbranch' => ['name' => 'main'],
                    'links' => [
                        'clone' => [
                            ['name' => 'https', 'href' => 'https://bitbucket.org/acme/private-app.git'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/git-providers", [
            'provider' => 'bitbucket',
            'token' => 'ATATT_test_secret_token_value',
            'email' => 'dev@example.com',
        ])
        ->assertCreated();

    $this->actingAs($owner)
        ->getJson("/api/v1/organizations/{$organization->id}/git-providers/bitbucket/repositories")
        ->assertOk()
        ->assertJsonPath('data.0.fullName', 'acme/private-app');

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'api.bitbucket.org/2.0/user/workspaces')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('dev@example.com:ATATT_test_secret_token_value'));
    });

    Http::assertNotSent(function ($request): bool {
        return (string) parse_url($request->url(), PHP_URL_PATH) === '/2.0/repositories';
    });
});

it('requires an atlassian email when storing a bitbucket token', function (): void {
    [$organization, $owner] = createGitProviderFixture();

    $this->actingAs($owner)
        ->postJson("/api/v1/organizations/{$organization->id}/git-providers", [
            'provider' => 'bitbucket',
            'token' => 'ATATT_test_secret_token_value',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
