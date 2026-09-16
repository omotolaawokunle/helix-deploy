<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Modules\Auth\DTOs\ForgotPasswordDTO;
use Illuminate\Support\Facades\Password;

final class SendPasswordResetLinkAction
{
    public function execute(ForgotPasswordDTO $dto): void
    {
        Password::broker()->sendResetLink([
            'email' => $dto->email,
        ]);
    }
}
