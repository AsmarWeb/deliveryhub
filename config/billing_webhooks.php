<?php

return [
    'max_attempts' => 12,
    'backoff_seconds' => [5, 15, 30, 60, 120, 300, 600, 900, 1800, 3600, 3600, 3600],
    'processing_timeout_seconds' => 120,
    'queue_lease_seconds' => 120,
];
