<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\DTOs\RegisterDTO;
use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Teams\Enums\TeamRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService implements AuthServiceInterface
{
    public function register(RegisterDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            $user = User::query()->create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password,
                'timezone' => 'UTC',
                'email_verified_at' => null,
            ]);

            $organization = Organization::query()->create([
                'name' => "{$dto->name}'s Organization",
                'slug' => $this->generateUniqueOrganizationSlug($dto->name),
                'settings' => [],
                'master_key_encrypted' => '{}',
            ]);

            $organization->generateAndStoreMasterKey();

            $organization->users()->attach($user->getKey(), [
                'role' => TeamRole::OWNER->value,
            ]);

            $user->forceFill([
                'current_organization_id' => $organization->getKey(),
            ])->save();

            $user->sendEmailVerificationNotification();

            AuditLog::record(
                operation: 'organization.created',
                resource: $organization,
                metadata: [
                    'organization_id' => (string) $organization->getKey(),
                ],
                afterState: [
                    'organization_id' => (string) $organization->getKey(),
                ],
            );

            AuditLog::record(
                operation: 'user.registered',
                resource: $user,
                metadata: [
                    'organization_id' => (string) $organization->getKey(),
                ],
                afterState: [
                    'organization_id' => (string) $organization->getKey(),
                ],
            );

            return $user->load('currentOrganizationRelation');
        });
    }

    public function login(LoginDTO $dto): User
    {
        $authenticated = Auth::attempt([
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        if (! $authenticated) {
            throw new InvalidCredentialsException('Invalid credentials provided.');
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->load('currentOrganizationRelation');
    }

    private function generateUniqueOrganizationSlug(string $name): string
    {
        $baseSlug = Str::of($name)->trim()->append("'s Organization")->slug()->value();
        $slug = $baseSlug;
        $counter = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
