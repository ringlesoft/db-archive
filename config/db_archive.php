<?php
return [
    /**
     * Database configuration for the backups.
     */
    'connection' => env('ARCHIVE_DB_CONNECTION', 'mysql'),

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
