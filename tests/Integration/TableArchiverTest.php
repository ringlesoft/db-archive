<?php

namespace Tests\Integration;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RingleSoft\DbArchive\Events\TableArchived;
use RingleSoft\DbArchive\Services\TableArchiver;
use Tests\TestCase;

class TableArchiverTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_archives_eligible_rows_and_reports_the_outcome(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00');
        DB::connection('source')->table('records')->insert([
            ['name' => 'old record', 'created_at' => Carbon::now()->subDays(31)],
            ['name' => 'recent record', 'created_at' => Carbon::now()->subDays(29)],
        ]);
        Event::fake();

        $result = TableArchiver::of('records')->archiveWithResult();

        $this->assertTrue($result->successful);
        $this->assertSame(1, $result->scanned);
        $this->assertSame(1, $result->archived);
        $this->assertSame(1, $result->removed);
        $this->assertSame(1, DB::connection('archive')->table('archive_records')->count());
        $this->assertSame('recent record', DB::connection('source')->table('records')->value('name'));
        Event::assertDispatched(TableArchived::class, fn (TableArchived $event) => $event->result->archived === 1);
    }

    public function test_it_soft_deletes_source_rows_when_configured(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00');
        DB::connection('source')->table('records')->insert([
            'name' => 'old record',
            'created_at' => Carbon::now()->subDays(31),
        ]);

        $result = TableArchiver::of('records')->withSettings([
            'soft_delete' => true,
        ])->archiveWithResult();

        $this->assertTrue($result->successful);
        $this->assertSame(1, $result->softDeleted);
        $this->assertSame(1, DB::connection('archive')->table('archive_records')->count());
        $this->assertNotNull(DB::connection('source')->table('records')->value('deleted_at'));

        $retry = TableArchiver::of('records')->withSettings(['soft_delete' => true])->archiveWithResult();

        $this->assertSame(0, $retry->scanned);
        $this->assertSame(1, DB::connection('archive')->table('archive_records')->count());
    }
}
