<?php

namespace  RingleSoft\DbArchive\Facades;

use Illuminate\Support\Facades\Facade;

class DbArchive extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RingleSoft\DbArchive\DbArchive::class;
    }
}
