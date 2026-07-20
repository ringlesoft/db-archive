<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RingleSoft\DbArchive\Traits\ArchivesData;

class ArchivedRecord extends Model
{
    use ArchivesData;

    protected $table = 'records';

    public $timestamps = false;
}
