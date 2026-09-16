<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Auth\DTOs\ResetPasswordDTO;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResetPasswordAction
{
    public function execute(ResetPasswordDTO $dto): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $dto->email,
                'password' => $dto->password,
                'password_confirmation' => $dto->password,
                'token' => $dto->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                $this->invalidateWebSessions($user);

                $organizationId = $user->current_organization_id;

                AuditLog::record(
                    operation: 'user.password_reset',
                    resource: $user,
                    metadata: is_string($organizationId) && $organizationId !== ''
                        ? ['organization_id' => $organizationId]
                        : [],
                    afterState: [
                        'tokens_revoked' => true,
                        'sessions_invalidated' => true,
                    ],
                );

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    private function invalidateWebSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        DB::table($table)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->delete();
    }
}
