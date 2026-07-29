<?php

return [
    'release_retention' => env('RELEASE_RETENTION', 5),
    'deployment_timeout_minutes' => env('DEPLOYMENT_TIMEOUT', 30),
    'build_timeout_minutes' => env('BUILD_TIMEOUT', 30),
    'ssh_timeout_seconds' => env('SSH_TIMEOUT', 30),
    'command_timeout_max_seconds' => env('COMMAND_TIMEOUT_MAX', 300),
    'ping_interval_minutes' => env('PING_INTERVAL', 5),
    'stuck_job_threshold_minutes' => env('STUCK_THRESHOLD', 35),
    'spa_url' => env('SPA_URL', 'http://localhost:5173'),
    // Public origin used in signed email links. Must match the URL users click (scheme, host, port).
    'verification_url_root' => env('VERIFICATION_URL_ROOT', env('APP_URL', 'http://localhost')),
];
