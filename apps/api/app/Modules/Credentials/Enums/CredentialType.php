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
    case GIT_PROVIDER_TOKEN = 'git_provider_token';
    case CLOUD_PROVIDER_CREDENTIAL = 'cloud_provider_credential';
    case DNS_PROVIDER_CREDENTIAL = 'dns_provider_credential';

    public function label(): string
    {
        return match ($this) {
            self::SSH_PRIVATE_KEY => 'SSH Private Key',
            self::SSH_PASSWORD => 'SSH Password',
            self::API_TOKEN => 'API Token',
            self::ENV_VAR => 'Environment Variable',
            self::REGISTRY_PASSWORD => 'Registry Password',
            self::GIT_PROVIDER_TOKEN => 'Git Provider Token',
            self::CLOUD_PROVIDER_CREDENTIAL => 'Cloud Provider Credential',
            self::DNS_PROVIDER_CREDENTIAL => 'DNS Provider Credential',
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
            self::GIT_PROVIDER_TOKEN => 'success',
            self::CLOUD_PROVIDER_CREDENTIAL => 'success',
            self::DNS_PROVIDER_CREDENTIAL => 'success',
        };
    }
}
