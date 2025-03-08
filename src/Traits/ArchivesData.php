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


}
