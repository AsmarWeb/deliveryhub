<?php

return [
    // Exact store hostnames approved by the operator. Never include wildcards.
    'feed_hosts' => array_values(array_filter(array_map('trim', explode(',', env('CATALOG_FEED_HOSTS', ''))))),
    'sync_minutes' => 15,
    'max_connections' => 5,
    'max_products' => 10000,
    'max_pages' => 100,
    'max_response_bytes' => 2097152,
];
