<?php

declare(strict_types=1);

namespace App\Modules\Daemons\Services;

use App\Modules\Daemons\Models\SupervisorProcess;

class SupervisorConfigGenerator
{
    public function generate(SupervisorProcess $daemon): string
    {
        $directory = $daemon->directory !== null && $daemon->directory !== ''
            ? $daemon->directory
            : '/var/www';

        return implode("\n", [
            '[program:'.$daemon->name.']',
            'command='.$daemon->command,
            'directory='.$directory,
            'user='.$daemon->user,
            'numprocs='.$daemon->processes,
            'autostart=true',
            'autorestart=true',
            'startretries=3',
            'redirect_stderr=true',
            'stdout_logfile=/var/log/supervisor/'.$daemon->name.'.log',
            'stdout_logfile_maxbytes=5MB',
            'stdout_logfile_backups=3',
            '',
        ]);
    }
}
