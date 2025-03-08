<?php
return [
    /**
     * Database configuration for the backups.
     *  (Make sure this connection exists in the Laravel's config/database.php file)
     */
    'connection' => env('ARCHIVE_DB_CONNECTION', 'mysql_archive'),

    /**
     * Default Settings used for archiving tables
     */
    'settings' => [
        'table_prefix' => null,
        'batch_size' => 1000,
        'archive_older_than_days' => 30,
        'date_column' => 'created_at',
        'soft_delete' => false,
        'conditions' => [],
        'primary_id' => 'id',
    ],

    /**
     * Enable logging of the archiving process
     */
    'enable_logging' => true,

    /**
     * Send notifications about archiving process
     */
    'notifications' => [
        'email' => 'admin@example.com',
    ],

    /**
     * The tables to be archived
     * - Use plain table names for default settings
     * - Use [ 'table_name' => [<settings>], ] to override the default settings
     */
    'tables' => [],

    /**
     * Enable job queuing and batching
     */
    "queueing" => [
        "enable_queuing" => true,
        "enable_batching" => false,
    ],
];
