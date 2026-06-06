<?php

declare(strict_types=1);

namespace App\Modules\Commands\Services;

use App\Modules\Commands\Enums\CommandWarningType;
use App\Modules\Commands\Exceptions\DangerousCommandException;

final class DangerousCommandGuard
{
    /**
     * @var list<array{pattern: string, label: string}>
     */
    private const BLOCKED_PATTERNS = [
        ['pattern' => '/rm\s+-rf\s+\/\*?$/i', 'label' => 'rm_rf_root'],
        ['pattern' => '/DROP\s+(DATABASE|TABLE|SCHEMA)/i', 'label' => 'sql_drop'],
        ['pattern' => '/passwd|chpasswd|usermod\s+-p/i', 'label' => 'credential_change'],
        ['pattern' => '/shutdown|reboot|halt|poweroff|init\s+[06]/i', 'label' => 'system_power'],
        ['pattern' => '/dd\s+if=.+of=\/dev\//i', 'label' => 'dd_disk'],
        ['pattern' => '/mkfs\.|fdisk\s+\/dev\//i', 'label' => 'disk_format'],
        ['pattern' => '/echo\s+.+>\s*\/etc\/(passwd|shadow|sudoers)/i', 'label' => 'etc_overwrite'],
        ['pattern' => '/>\s*\/dev\/(sda|sdb|hda)/i', 'label' => 'raw_disk_write'],
    ];

    /**
     * @var list<array{pattern: string, type: CommandWarningType}>
     */
    private const WARN_PATTERNS = [
        ['pattern' => '/^\s*sudo\s+/i', 'type' => CommandWarningType::SUDO],
        ['pattern' => '/mysql\s+-e|psql\s+-c/i', 'type' => CommandWarningType::DATABASE_CLI],
        ['pattern' => '/wget\s+|curl\s+/i', 'type' => CommandWarningType::REMOTE_DOWNLOAD],
        ['pattern' => '/\|\s*sh|\|\s*bash/i', 'type' => CommandWarningType::PIPED_SHELL],
        ['pattern' => '/rm\s+-rf\s+(?!\/)/i', 'type' => CommandWarningType::DESTRUCTIVE_RM],
    ];

    public function check(string $command): void
    {
        foreach (self::BLOCKED_PATTERNS as $blocked) {
            if (preg_match($blocked['pattern'], $command) === 1) {
                throw new DangerousCommandException(
                    blockedPattern: $blocked['label'],
                    message: 'This command is blocked for safety reasons.',
                );
            }
        }
    }

    public function warn(string $command): bool
    {
        return $this->warningType($command) !== null;
    }

    public function warningType(string $command): ?CommandWarningType
    {
        foreach (self::WARN_PATTERNS as $warn) {
            if (preg_match($warn['pattern'], $command) === 1) {
                return $warn['type'];
            }
        }

        return null;
    }
}
