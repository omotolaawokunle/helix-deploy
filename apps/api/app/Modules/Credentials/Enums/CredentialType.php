<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Enums;

enum CredentialType: string
{
    case SSH_PRIVATE_KEY = 'ssh_private_key';
    case SECRET = 'secret';
}
