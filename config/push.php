<?php
return [
    'enabled' => (bool) env('EXPO_PUSH_ENABLED', false),
    'project_id' => env('EXPO_PUSH_PROJECT_ID'),
    'access_token' => env('EXPO_PUSH_ACCESS_TOKEN'),
];
