<?php
return [
    'enabled' => env('DISPATCH_ENABLED', true),
    'ai_enabled' => env('DISPATCH_AI_ENABLED', false),
    'radius_km' => (float) env('DISPATCH_RADIUS_KM', 30),
    'presence_seconds' => 180,
    'location_seconds' => 300,
    'offer_seconds' => 120,
];
