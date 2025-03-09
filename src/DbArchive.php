<?php

namespace RingleSoft\DbArchive;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use RingleSoft\DbArchive\Jobs\ArchiveTableJob;
use RingleSoft\DbArchive\Utility\Logger;
use Throwable;

class DbArchive
{

    public function __construct()
    {
    }

    /**
     * @return bool|Batch
     * @throws Throwable
     */
    public function archive(): bool|Batch
    {
        $availableTables = Config::get('db_archive.tables');
        $jobData = [];
        foreach ($availableTables as $key => $value) {
            if(is_numeric($key)){
                $table = $value;
                $settings = [];
            } else {
                $table = $key;
                $settings = $value;
            }
            $jobData[] = [
                'table' => $table,
                'settings' => $settings
            ];
        }

        if(Config::get('db_archive.queueing.enable_queuing')){
            if(Config::get('db_archive.queueing.enable_batching')) {
                $batch = Bus::batch([]);
                try {
                    foreach($jobData as $data){
                        $batch->add(new ArchiveTableJob($data['table'], $data['settings']));
                    }
                    $batch->then(function (Batch $batch) {
                        Logger::info('All jobs in the batch completed successfully.');
                    })->catch(function (Batch $batch, Throwable $e) {
                        Logger::error('Batch failed: ' . $e->getMessage());
                    })->finally(function (Batch $batch) {
                        Logger::info('Batch processing finished.');
                    })->then(function (Batch $batch) {
                        return $batch->id;
                    })->dispatch();
                } catch (Throwable $e) {
                    Logger::error('Batch failed: ' . $e->getMessage());
                    throw $e;
                }
            } else {
                foreach($jobData as $data){
                    ArchiveTableJob::dispatch($data['table'], $data['settings']);
                }
            }
        } else {
            foreach($jobData as $data){
                ArchiveTableJob::dispatchSync($data['table'], $data['settings']);
            }
        }
        return true;
    }

}
