<?php
return [
    /**
     * Database configuration for the backups.
     */
    'connection' => [
        'connection' => env('ARCHIVE_DB_CONNECTION', 'mysql'),
        'host' => env('ARCHIVE_DB_HOST', '127.0.0.1'),
        'port' => env('ARCHIVE_DB_PORT', '3306'),
        'database' => env('ARCHIVE_DB_DATABASE', 'laravel_backup'),
        'username' => env('ARCHIVE_DB_USERNAME', 'root'),
        'password' => env('ARCHIVE_DB_PASSWORD', ''),
    ],

    'backup' => [
        'table_prefix' => null,
        'batch_size' => 1000,
        'archive_older_than_days' => 30,
        'date_column' => 'created_at',
        'soft_delete' => false,
    ],

    'enable_logging' => true,

    'notifications' => [
        'email' => 'admin@example.com',
    ],

    'tables' => ['users'],


];
