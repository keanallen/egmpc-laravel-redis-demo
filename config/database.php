<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql', 'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'), 'database' => env('DB_DATABASE', 'attendance_demo'),
            'username' => env('DB_USERNAME', 'attendance_demo'), 'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''), 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ],
        // Optional test database; the application defaults to MySQL.
        'sqlite' => [
            'driver' => 'sqlite', 'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '', 'foreign_key_constraints' => true,
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => ['prefix' => env('REDIS_PREFIX', 'attendance_demo_database_')],
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'), 'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379), 'database' => env('REDIS_CACHE_DB', 1),
        ],
    ],
];
