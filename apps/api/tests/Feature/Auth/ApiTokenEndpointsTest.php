<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Str;

/**
 * @return array{Organization, User}
 */
function createApiTokenFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'API Token Org',
        'slug' => 'api-token-'.Str::random(6),
        'master_key_encrypted' => '{}',
        'settings' => [],
    ]);
    $organization->generateAndStoreMasterKey();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_organization_id' => (string) $organization->getKey(),
    ]);
    $organization->users()->attach($user->getKey(), ['role' => TeamRole::OWNER->value]);

    return [$organization, $user];
}

it('creates lists and revokes api tokens with audit logs', function (): void {
    [$organization, $user] = createApiTokenFixture();

    $createResponse = $this->actingAs($user)
        ->postJson('/api/v1/auth/tokens', [
            'name' => 'CI deploy',
            'ability' => 'full',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'CI deploy')
        ->assertJsonStructure(['plainTextToken']);

    $tokenId = (string) $createResponse->json('data.id');
    $plainTextToken = (string) $createResponse->json('plainTextToken');

    expect(AuditLog::query()->where('operation', 'api_token.created')->exists())->toBeTrue();

    $this->actingAs($user)
        ->getJson('/api/v1/auth/tokens')
        ->assertOk()
        ->assertJsonFragment(['id' => $tokenId, 'name' => 'CI deploy']);

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/v1/organizations')
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson("/api/v1/auth/tokens/{$tokenId}")
        ->assertNoContent();

    expect(AuditLog::query()->where('operation', 'api_token.revoked')->exists())->toBeTrue();
});

it('blocks read only tokens from mutating requests', function (): void {
    [$organization, $user] = createApiTokenFixture();

    $token = $user->createToken('read-only', ['read']);

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/v1/organizations')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->postJson("/api/v1/organizations/{$organization->id}/server-groups", [
            'name' => 'Blocked Group',
        ])
        ->assertForbidden();
});
