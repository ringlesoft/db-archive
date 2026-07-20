<?php

namespace RingleSoft\DbArchive\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RingleSoft\DbArchive\Services\ArchiveResult;

final class TableArchived
{
    use Dispatchable;

    public function __construct(public readonly ArchiveResult $result)
    {
    }
}
