<?php

namespace RingleSoft\DbArchive\Console\Commands;

use Illuminate\Bus\Batch;
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
    protected $signature = 'db-archive:batch-status {batchId}';

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

        if ($batch) {
            $this->info("Batch ID: " . $batch->id);
            $status = $this->getBatchStatus($batch);
            $this->info("Batch status: " . $status);
            $this->info("Batch progress: {$batch->progress()}%");
            $this->info("Total jobs: {$batch->totalJobs}");
            $this->info("Pending jobs: {$batch->pendingJobs}");
            $this->info("Failed jobs: {$batch->failedJobs}");
        } else {
            $this->error("Batch not found");
        }
    }

    /**
     * @param Batch $batch
     * @return string
     */
    private function getBatchStatus(Batch $batch): string
    {
        if ($batch->cancelled()) {
            return 'Cancelled';
        }
        return $batch->finished() ? ($batch->hasFailures() ? 'finished with failures' : 'finished') : 'running';
    }
}
