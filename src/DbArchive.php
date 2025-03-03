<?php

namespace RingleSoft\DbArchive;

class DbArchive
{
    private string $activeConnection;
    private string $archiveConnection;

    public function __construct()
    {
        $this->activeConnection = Config::get('database.default');
    }

    public function archive()
    {

    }


}
