<?php

namespace RingleSoft\DbArchive\Console\Commands;
use Illuminate\Console\Command;
use RingleSoft\DbArchive\Facades\DbArchive;

class ArchiveDataCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-archiver:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old database entries';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $batchId = DbArchive::archive();
        if ($batchId) {
            $this->info("Batch ID: " . $batchId);
        }
    }
}
