<?php

return [
    'release_retention' => env('RELEASE_RETENTION', 5),
    'deployment_timeout_minutes' => env('DEPLOYMENT_TIMEOUT', 30),
    'ssh_timeout_seconds' => env('SSH_TIMEOUT', 30),
    'ping_interval_minutes' => env('PING_INTERVAL', 5),
    'stuck_job_threshold_minutes' => env('STUCK_THRESHOLD', 35),
    'spa_url' => env('SPA_URL', 'http://localhost:5173'),
];
