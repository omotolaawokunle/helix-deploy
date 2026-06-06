<?php

declare(strict_types=1);

return [
    'export' => [
        'queue_threshold' => (int) env('AUDIT_EXPORT_QUEUE_THRESHOLD', 10_000),
    ],
];
