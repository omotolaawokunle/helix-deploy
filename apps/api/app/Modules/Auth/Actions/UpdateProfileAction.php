<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Auth\DTOs\UpdateProfileDTO;
use Illuminate\Support\Str;

final class UpdateProfileAction
{
    public function execute(User $user, UpdateProfileDTO $dto): User
    {
        $beforeState = [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
        ];

        $emailChanged = Str::lower($dto->email) !== Str::lower((string) $user->email);

        $user->name = $dto->name;
        $user->timezone = $dto->timezone;

        if ($emailChanged) {
            $user->email = $dto->email;
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        AuditLog::record(
            operation: 'user.profile_updated',
            resource: $user,
            beforeState: $beforeState,
            afterState: [
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone,
                'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
                'emailChanged' => $emailChanged,
            ],
        );

        return $user->refresh();
    }
}
