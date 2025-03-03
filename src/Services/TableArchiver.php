<?php

namespace RingleSoft\DbArchive\Services;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TableArchiver
{
    public string $table;
    public string $archiveTable;
    public ?string $activeConnection;
    public ?string $archiveConnection;
    public ArchiveSettings $settings;
    public Carbon $cutoffDate;

    public function __construct(string $table, ?array $settings = [])
    {
        if ($settings) {
            $this->withSettings($settings);
        }
        $this->table = $table;
        $this->archiveTable = $this->settings->tablePrefix ? ($this->settings->tablePrefix . '_' . $this->table) : $this->table;
        $this->activeConnection = Config::get('database.connections')[Config::get('database.default')];
        $this->archiveConnection = Config::get('db_archive.connection');

    }

    public static function of(string $table): self
    {
        return new self($table);
    }

    public function withSettings(array $settings): self
    {
        if ($settings && count($settings) > 0) {
           $this->settings = ArchiveSettings::fromArray($settings);
        }
        return $this;
    }


    public function archive()
    {
        try {
            // Calculate the cutoff date
            $this->cutoffDate = Carbon::now()->subDays($this->settings->archiveOlderThanDays);

            // Log the start of the archiving process
            $this->log("Archiving table: " . $this->table);

            // Use chunking to process records in batches
            DB::connection($this->activeConnection)->table($this->table)
                ->where($this->settings->dateColumn, '<', $this->cutoffDate)
                ->orderBy($this->settings->dateColumn) // Ensure consistent ordering
                ->chunkById($this->settings->batchSize, function ($rows, $index){

                    // Start a database transaction for the destination connection
                    DB::connection($this->archiveConnection)->beginTransaction();

                    try {
                        // Insert rows into the destination table
                        DB::connection($this->archiveConnection)->table($this->archiveTable)->insert($rows->toArray());

                        // Delete the rows from the source table
                        $ids = $rows->pluck('id')->toArray();
                        DB::connection($this->activeConnection)->table($this->table)->whereIn('id', $ids)->delete();

                        // Commit the transaction
                        DB::connection($this->archiveConnection)->commit();

                        $this->log("Archived batch of " . count($rows) . " rows from {$this->activeConnection}: {$this->table} to {$this->archiveConnection} : {$this->archiveTable}");
                    } catch (Exception $e) {
                        // Rollback the transaction in case of an error
                        DB::connection($this->archiveConnection)->rollBack();

                        // Log the error
                        Log::error("Error archiving batch: " . $e->getMessage());
                    }
                });

            // Log the completion of the archiving process
            Log::info("Archiving process completed for table: {$this->table}");
        } catch (Exception $e) {
            // Log any unexpected errors
            Log::error("Archiving process failed: " . $e->getMessage());
        }
    }

    /**
     * @param $data
     * @param String|null $type
     * @return void
     */
    private function log($data, ?String $type = "info"): void
    {
        if(Config::get('db_archive.enable_logging')) {
            // TODO: modify the method
            Log::debug($data);
        }
    }

}

