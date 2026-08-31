<?php

return [
    'admin' => [
        'name' => env('RAMS_ADMIN_NAME'),
        'username' => env('RAMS_ADMIN_USERNAME'),
        'email' => env('RAMS_ADMIN_EMAIL'),
        'password' => env('RAMS_ADMIN_PASSWORD'),
    ],
    'demo_accounts' => [
        'enabled' => (bool) env('RAMS_SEED_DEMO_ACCOUNTS', false),
        'daop_password' => env('RAMS_DAOP_PASSWORD'),
    ],
    'imports' => [
        'disk' => env('RAMS_IMPORT_DISK', 'local'),
    ],
];
