<?php

namespace RingleSoft\DbArchive\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Prompts\Progress;
use RingleSoft\DbArchive\Services\SetupService;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-archive:setup {--force : Force recreating tables when existing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the package: Copy the schema of an existing table to create a new table with an identical schema.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $force = (bool)$this->option('force');

        $tablePrefix = Config::get('db_archive.prefix');
        $archiveConnectionName = Config::get('db_archive.connection');
        $activeConnection = DB::connection();
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

        if ($activeConnection->getDatabaseName() === $archiveConnection->getDatabaseName()) {
            // If the same database is used for backup, maike sure a prefix is set
            if ($tablePrefix === null || $tablePrefix === "") {
                error("Archive database connection '$archiveConnectionName' is the same as the active connection. Please set a table prefix.");
                return;
            }
            if (!confirm("Archive database connection '$archiveConnectionName' is the same as the active connection. Do you want to continue?")) {
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
        foreach ($availableTales as $key => $value) {
            if (is_numeric($key)) {
                $table = $value;
            } else {
                $table = $key;
            }
            if ($setupService->archiveTableExists($table)) {
                warning("Table already exists. Use --force to overwrite.");
                if (!$force) {
                    $this->info("Table already exists. Use --force to overwrite.");
                    $progressBar->advance();
                    continue;
                } else {
                    if (!$setupService->dropArchiveTable($table)) {
                        $this->info("Failed to drop table: " . $table);
                    }
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
