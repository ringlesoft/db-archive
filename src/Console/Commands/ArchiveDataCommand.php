<?php

namespace RingleSoft\DbArchive\Console\Commands;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use RingleSoft\DbArchive\Facades\DbArchive;
use RingleSoft\DbArchive\Jobs\SendNotificationJob;
use RingleSoft\DbArchive\Utility\Logger;
use Throwable;

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
        try {
            $archiveResult = DbArchive::archive();
            if ($archiveResult instanceof Batch) {
                $this->info("Batch ID: " . $archiveResult->id);
                Logger::debug($archiveResult);
                $email = Config::get('db_archive.notifications.email', null);
                if($email) {
                    try {
                        SendNotificationJob::dispatch($email);
                    } catch (Throwable $e) {
                        Logger::error($e->getMessage());
                    }
                }
            } else {
                $this->info("No archived data found");
            }
        } catch (Throwable $e) {

        }

    }
}
