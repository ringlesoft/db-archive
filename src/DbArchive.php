<?php

namespace RingleSoft\DbArchive;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RingleSoft\DbArchive\Jobs\ArchiveTableJob;
use RingleSoft\DbArchive\Services\TableArchiver;
use Throwable;

class DbArchive
{

    public function __construct()
    {
    }

    public function archive(): ?int
    {
        $availableTables = Config::get('db_archive.tables');
        $batch = Bus::batch([]);
        foreach ($availableTables as $key => $value) {
            if(is_numeric($key)){
                $table = $value;
                $settings = null;
            } else {
                $table = $key;
                $settings = $value;
            }

            $batch->add(new ArchiveTableJob($table, $settings));
        }

        try {
            $batch->then(function (Batch $batch) {
                // All jobs completed successfully
                Log::info('All jobs in the batch completed successfully.');
            })->catch(function (Batch $batch, Throwable $e) {
                // Handle job failure
                Log::error('Batch failed: ' . $e->getMessage());
            })->finally(function (Batch $batch) {
                // Execute after the batch has finished (success or failure)
                Log::info('Batch processing finished.');
            })->dispatch();
            return $batch->id;
        } catch (Throwable $e) {
            Log::error('Batch failed: ' . $e->getMessage());
        }
        return null;
    }


}
