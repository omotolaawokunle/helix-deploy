<?php

declare(strict_types=1);

namespace App\Modules\Auth\Contracts;

use App\Models\User;
use App\Modules\Auth\DTOs\LoginDTO;
use App\Modules\Auth\DTOs\RegisterDTO;

interface AuthServiceInterface
{
    public function register(RegisterDTO $dto): User;

    public function login(LoginDTO $dto): User;
}
