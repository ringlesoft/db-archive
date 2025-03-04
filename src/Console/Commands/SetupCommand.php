<?php

namespace RingleSoft\DbArchive\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Prompts\Progress;
use RingleSoft\DbArchive\Services\SetupService;
use function Laravel\Prompts\alert;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-archive:setup {flag? : Options}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy the schema of an existing table to create a new table with an identical schema.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $flag = $this->argument('flag');
        if ($flag === '--force' || $flag === '-f') {
            $force = true;
        } else {
            $force = false;
        }
        $archiveConnectionName = Config::get('db_archive.connection');
        $archiveConnection = DB::connection($archiveConnectionName);

        if (!$archiveConnection) {
            $this->error("Archive database connection '$archiveConnectionName' does not exist.");
            return;
        }

        $setupService = new SetupService();
        $archiveDatabaseName = $archiveConnection->getDatabaseName();
        if (!$setupService->archiveDatabaseExists()) {
            $this->info("Archive database '$archiveDatabaseName' does not exist.");
            if (select('Do you want to create it?', ['Yes', 'No'], 'No') === 'Yes') {
                try {
                    if (!$setupService->cloneDatabase()) {
                        $this->error("Could not create archive database.");
                        return;
                    } else {
                        info("Archive database '$archiveDatabaseName' created.");
                    }
                } catch (Exception $e) {
                    warning("Could not create archive database due to error: " . $e->getMessage());
                    return;
                }
            } else {
                return;
            }
        }

        $availableTales = Config::get('db_archive.tables', []);
        if (empty($availableTales)) {
            $this->error("No tales found in config file.");
            return;
        }

        $progressBar = new Progress("Preparing Tables", count($availableTales));
        $progressBar->start();
        foreach ($availableTales as $table) {
            if ($setupService->archiveTableExists($table)) {
                if (!$force) {
                    info("Table already exists. Use --force to overwrite.");
                    $progressBar->advance();
                    continue;
                }
            }
            try {
                $setupService->cloneTable($table);
            } catch (Exception $e) {
                $this->error("Failed to create table: " . $e->getMessage());
                return;
            } finally {
                $progressBar->advance();
                $progressBar->render();
            }
        }
    }


}
