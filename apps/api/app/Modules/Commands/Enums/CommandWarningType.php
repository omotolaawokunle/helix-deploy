<?php

declare(strict_types=1);

namespace App\Modules\Commands\Enums;

enum CommandWarningType: string
{
    case SUDO = 'sudo';
    case DATABASE_CLI = 'database_cli';
    case REMOTE_DOWNLOAD = 'remote_download';
    case PIPED_SHELL = 'piped_shell';
    case DESTRUCTIVE_RM = 'destructive_rm';

    public function reason(): string
    {
        return match ($this) {
            self::SUDO => 'This command uses sudo and requires additional confirmation.',
            self::DATABASE_CLI => 'This command runs a direct database query and requires additional confirmation.',
            self::REMOTE_DOWNLOAD => 'This command downloads content from an external URL and requires additional confirmation.',
            self::PIPED_SHELL => 'This command pipes output to a shell interpreter and requires additional confirmation.',
            self::DESTRUCTIVE_RM => 'This command uses recursive force delete and requires additional confirmation.',
        };
    }
}
