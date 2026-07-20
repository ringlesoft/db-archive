<?php

namespace RingleSoft\DbArchive;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use RingleSoft\DbArchive\Jobs\ArchiveTableJob;
use RingleSoft\DbArchive\Jobs\SendNotificationJob;
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
        $availableTables = Config::get('db_archive.tables', []);
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
                try {
                    $jobs = array_map(
                        static fn (array $data) => new ArchiveTableJob($data['table'], $data['settings']),
                        $jobData,
                    );
                    $email = Config::get('db_archive.notifications.email');

                    return Bus::batch($jobs)->then(function (Batch $batch) use ($email) {
                        Logger::info('All jobs in the batch completed successfully.');
                        if ($email) {
                            SendNotificationJob::dispatch($email, true);
                        }
                    })->catch(function (Batch $batch, Throwable $e) {
                        Logger::error('Batch failed: ' . $e->getMessage());
                        $email = Config::get('db_archive.notifications.email');
                        if ($email) {
                            SendNotificationJob::dispatch($email, false);
                        }
                    })->finally(function (Batch $batch) {
                        Logger::info('Batch processing finished.');
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
