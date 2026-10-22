<?php

return [
    'public_url' => env('RASLO_INTEGRATION_PUBLIC_URL') ?: env('APP_URL', 'http://localhost'),
    'tracking_days' => 90,
    'max_attempts' => 12,
    'backoff_seconds' => [60, 120, 300, 600, 900, 1800, 3600, 3600, 3600, 3600, 3600, 3600],
];
