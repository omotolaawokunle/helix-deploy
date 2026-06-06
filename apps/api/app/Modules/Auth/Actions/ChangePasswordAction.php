<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Auth\DTOs\ChangePasswordDTO;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordAction
{
    public function execute(User $user, ChangePasswordDTO $dto): void
    {
        if (! Hash::check($dto->currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['Current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $dto->password,
        ])->save();

        AuditLog::record(
            operation: 'user.password_changed',
            resource: $user,
            afterState: [
                'changedBy' => (string) $user->getKey(),
            ],
        );
    }
}
