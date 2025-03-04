<?php

namespace RingleSoft\DbArchive\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $this->withSettings($settings ?? []);
        $this->table = $table;
        $this->activeConnection = Config::get("database.default");
        $this->archiveConnection = Config::get('db_archive.connection');
        $this->archiveTable = $this->settings->tablePrefix ? ($this->settings->tablePrefix . '_' . $this->table) : $this->table;

    }

    public static function of(string $table): self
    {
        return new self($table);
    }

    public function withSettings(array $settings): self
    {
        $this->settings = ArchiveSettings::fromArray($settings);
        return $this;
    }


    public function archive()
    {
        $this->cutoffDate = Carbon::now()->subDays($this->settings->archiveOlderThanDays);
        $this->log("Archiving table: " . $this->table);
        $sourceConnection = DB::connection($this->activeConnection);
        $archiveConnection = DB::connection($this->archiveConnection);
        $sourceTableName = $this->table;
        $archiveTableName = $this->archiveTable;
        $chunkSize = $this->settings->batchSize;
        $dateColumn = $this->settings->dateColumn;
        $conditions = $this->settings->conditions;
        $primaryId = $this->settings->primaryId ?? 'id';

        try {
            DB::beginTransaction(); // Start a transaction to ensure atomicity
            $sourceConnection->table($sourceTableName)
                ->where($dateColumn, '<', $this->cutoffDate)
                ->when(count($conditions), function ($query) use ($conditions) {
                    foreach ($conditions as $key => $value) {
                        if (is_numeric($key) && is_array($value) && (count($value) && count($value) <= 3)) {
                            $query->where(...$value);
                        } else {
                            $query->where($key, $value);
                        }
                    }
                })
                ->orderBy($dateColumn) // Assuming 'id' is your primary key and is auto-incrementing for efficient chunking
                ->chunkById($chunkSize, function ($sourceRecords) use ($sourceTableName, $archiveTableName, $archiveConnection, $sourceConnection, $primaryId) {
                    $dataToArchive = [];
                    $idsToDelete = [];

                    foreach ($sourceRecords as $record) {
                        $dataToArchive[] = (array)$record; // Exclude 'id' if you want auto-increment in archive table
                        $idsToDelete[] = $record->{$primaryId};
                    }

                    if (!empty($dataToArchive)) {
                        $archiveConnection->table($archiveTableName)->insert($dataToArchive); // Efficient bulk insert
                    }

                    if (!empty($idsToDelete)) {
                        $sourceConnection->table($sourceTableName)
                            ->whereIn($primaryId, $idsToDelete)
                            ->delete(); // Efficient bulk delete based on IDs
                    }
                });

            DB::commit(); // Commit the transaction if everything was successful
            return true;

        } catch (QueryException $e) {
            DB::rollBack(); // Rollback the transaction in case of any error
            report($e); // Log the exception for debugging
            return false; // Or throw an exception if you want to handle it differently up the call stack
        } catch (Exception $e) {
            DB::rollBack(); // Rollback transaction for other exceptions as well
            report($e);
            return false; // Or throw exception
        } catch (Throwable $e) {
            DB::rollBack(); // Rollback transaction for other exceptions as well
            report($e);
            return false; // Or throw exception
        }
    }

    /**
     * @param $data
     * @param String|null $type
     * @return void
     */
    private function log($data, ?string $type = "info"): void
    {
        if (Config::get('db_archive.enable_logging')) {
            // TODO: modify the method
            Log::debug($data);
        }
    }

}

