<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Create Users
    |--------------------------------------------------------------------------
    |
    | We don't create users automatically when seeding roles and permissions.
    | Users will be created through the authentication API.
    |
    */

    'create_users' => false,

    /*
    |--------------------------------------------------------------------------
    | Truncate Tables
    |--------------------------------------------------------------------------
    |
    | Never truncate authorization tables automatically.
    | This makes the seeder safer to run repeatedly.
    |
    */

    'truncate_tables' => false,

    /*
    |--------------------------------------------------------------------------
    | Roles Structure
    |--------------------------------------------------------------------------
    |
    | Raslo roles and their permissions.
    |
    */

    'roles_structure' => [

        'admin' => [
            'users' => 'c,r,u,d',
            'merchants' => 'c,r,u,d',
            'drivers' => 'c,r,u,d',
            'deliveries' => 'c,r,u,d',
            'pricing' => 'c,r,u,d',
            'reports' => 'r',
            'settings' => 'r,u',
            'profile' => 'r,u',
        ],

        'merchant' => [
            'deliveries' => 'c,r,u,d',
            'pricing' => 'r',
            'profile' => 'r,u',
        ],

        'driver' => [
            'deliveries' => 'r,u',
            'profile' => 'r,u',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Map
    |--------------------------------------------------------------------------
    */

    'permissions_map' => [
        'c' => 'create',
        'r' => 'read',
        'u' => 'update',
        'd' => 'delete',
    ],
];
