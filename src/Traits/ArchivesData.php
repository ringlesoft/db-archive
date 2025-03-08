<?php

namespace RingleSoft\DbArchive\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * @mixin Model
 */
Trait ArchivesData
{

    /**
     * Return a model instance that uses the archive database connection.
     * @return static
     */
    public static function archived(): static
    {
        return (new static())->setConnection(Config::get('db_archive.connection'));
    }


    /**
     * Get archive settings for the model
     * @return array
     */
    public static function getArchiveSettings(): array
    {
        $tableName = (new static())->getTable();
        $defaultSettings = Config::get('db_archive.settings');
        $tableSettings = Config::get('db_archive.tables') ?? [];
        return array_merge($defaultSettings, $tableSettings[$tableName] ?? []);
    }


    /**
     * Check if archiving is enabled for the model
     * @return bool
     */
    public static function isArchivingEnabled(): bool
    {
        $tables = Config::get('db_archive.tables', []);
       return array_key_exists((new static())->getTable(), $tables) || in_array((new static())->getTable(), $tables, true);
    }


}
