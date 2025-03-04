<?php

namespace RingleSoft\DbArchive\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use RingleSoft\DbArchive\Services\SetupService;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-archive:setup {flag}';

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
        $archiveConnection = Config::get('db_archive.connection');
        $actualConnection = Config::get("database.connections.$archiveConnection");
        if (!$actualConnection) {
            $this->error("Archive database connection '$archiveConnection' does not exist.");
            return;
        }

        $setupService = new SetupService();
        $archiveDatabaseName = Config::get("database.connections.$archiveConnection.database");
        if (!$setupService->archiveDatabaseExists()) {
            $this->error("Archive database '$archiveDatabaseName' does not exist.");
            if ($this->option('Do you want to create it?')) {
                if (!$setupService->cloneDatabase()) {
                    $this->error("Could not create archive database.");
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
        foreach ($availableTales as $table) {
            $this->info("Preparing Table: $table");
            if ($setupService->archiveTableExists($table)) {
                if (!$force) {
                    $this->error("Table already exists. Use --force to overwrite.");
                    return;
                }
            }
            try {
                $setupService->cloneTable($table);
            } catch (\Exception $e) {
                $this->error("Failed to create table: " . $e->getMessage());
                return;
            }


        }
    }


}
