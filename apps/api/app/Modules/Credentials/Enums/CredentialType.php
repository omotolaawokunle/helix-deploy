<?php

declare(strict_types=1);

namespace App\Modules\Credentials\Enums;

enum CredentialType: string
{
    case SSH_PRIVATE_KEY = 'ssh_private_key';
    case SSH_PASSWORD = 'ssh_password';
    case API_TOKEN = 'api_token';
    case ENV_VAR = 'env_var';
    case REGISTRY_PASSWORD = 'registry_password';

    public function label(): string
    {
        return match ($this) {
            self::SSH_PRIVATE_KEY => 'SSH Private Key',
            self::SSH_PASSWORD => 'SSH Password',
            self::API_TOKEN => 'API Token',
            self::ENV_VAR => 'Environment Variable',
            self::REGISTRY_PASSWORD => 'Registry Password',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SSH_PRIVATE_KEY => 'info',
            self::SSH_PASSWORD => 'warning',
            self::API_TOKEN => 'success',
            self::ENV_VAR => 'secondary',
            self::REGISTRY_PASSWORD => 'danger',
        };
    }
}
