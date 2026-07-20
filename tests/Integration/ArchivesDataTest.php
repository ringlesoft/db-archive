<?php

namespace Tests\Integration;

use Tests\Fixtures\ArchivedRecord;
use Tests\TestCase;

class ArchivesDataTest extends TestCase
{
    public function test_it_uses_the_prefixed_archive_table(): void
    {
        $archived = ArchivedRecord::archived();

        $this->assertSame('archive', $archived->getConnectionName());
        $this->assertSame('archive_records', $archived->getTable());
    }
}
