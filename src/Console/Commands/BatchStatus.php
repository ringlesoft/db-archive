<?php

namespace RingleSoft\DbArchive\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use RingleSoft\DbArchive\Facades\DbArchive;

class BatchStatusCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-archiver:batch-status {batchId}';

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
        $batchId = $this->argument('batchId');
        $batch = Bus::findBatch($batchId);

        if($batch){
            $this->info("Batch ID: " . $batch->id);
            $this->info("Batch status: " . $batch->status);
            $this->info("Batch progress: {$batch->progress()}%");
            $this->info("Total jobs: {$batch->totalJobs}");
            $this->info("Pending jobs: {$batch->pendingJobs}");
            $this->info("Failed jobs: {$batch->failedJobs}");
        } else {
            $this->error("Batch not found");
        }
    }
}
