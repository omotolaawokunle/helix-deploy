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
            'process_name=%(program_name)s_%(process_num)02d',
            'command='.$daemon->command,
            'directory='.$directory,
            'user='.$daemon->user,
            'numprocs='.$daemon->processes,
            'autostart=true',
            'autorestart=true',
            'startsecs=1',
            'startretries=3',
            'stopwaitsecs=3600',
            'redirect_stderr=true',
            'stdout_logfile=/var/log/supervisor/'.$daemon->name.'.log',
            'stdout_logfile_maxbytes=5MB',
            'stdout_logfile_backups=3',
            '',
        ]);
    }
}
