<?php

return [
    'project' => env('OBSERVABILITY_PROJECT', env('APP_NAME', 'deployer')),
    'env' => env('APP_ENV', 'production'),

    'loki' => [
        'enabled' => env('OBSERVABILITY_LOKI_ENABLED', true),
        'endpoint' => env('LOKI_URL', 'http://127.0.0.1:3100'),
    ],

    'metrics' => [
        'enabled' => env('OBSERVABILITY_METRICS_ENABLED', true),
        'route' => env('OBSERVABILITY_METRICS_ROUTE', '/metrics'),
        'allowed_ips' => explode(',', env('OBSERVABILITY_METRICS_ALLOWED_IPS', '127.0.0.1')),

        'redis' => [
            'host' => env('OBSERVABILITY_REDIS_HOST', env('REDIS_HOST', '127.0.0.1')),
            'port' => (int) env('OBSERVABILITY_REDIS_PORT', env('REDIS_PORT', 6379)),
            'password' => env('OBSERVABILITY_REDIS_PASSWORD', env('REDIS_PASSWORD')),
            'database' => (int) env('OBSERVABILITY_REDIS_DB', 5),
            'timeout' => 0.1,
            'read_timeout' => 10,
            'persistent_connections' => false,
        ],
    ],
];
