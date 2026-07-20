<?php

namespace RingleSoft\DbArchive\Services;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RingleSoft\DbArchive\Utility\Logger;
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
        if (isset($this->table)) {
            $this->archiveTable = $this->settings->tablePrefix
                ? $this->settings->tablePrefix . '_' . $this->table
                : $this->table;
        }

        return $this;
    }


    /**
     * @return bool
     * @throws Throwable
     */
    public function archive(): bool
    {
        $this->cutoffDate = Carbon::now()->subDays($this->settings->archiveOlderThanDays);
        Logger::info("Archiving table: " . $this->table);
        $sourceConnection = DB::connection($this->activeConnection);
        $archiveConnection = DB::connection($this->archiveConnection);
        $sourceTableName = $this->table;
        $archiveTableName = $this->archiveTable;
        $chunkSize = $this->settings->batchSize;
        $dateColumn = $this->settings->dateColumn;
        $conditions = $this->settings->conditions;
        $primaryId = $this->settings->primaryId ?? 'id';

        try {
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
                ->orderBy($dateColumn)
                ->chunkById($chunkSize, function ($sourceRecords) use ($sourceTableName, $archiveTableName, $archiveConnection, $sourceConnection, $primaryId) {
                    $dataToArchive = [];
                    $idsToDelete = [];

                    foreach ($sourceRecords as $record) {
                        $dataToArchive[] = (array)$record;
                        $idsToDelete[] = $record->{$primaryId};
                    }

                    if (!empty($dataToArchive)) {
                        // Re-running a partially completed archive updates the same rows
                        // instead of creating duplicates before the source delete retries.
                        $columnsToUpdate = array_values(array_diff(array_keys($dataToArchive[0]), [$primaryId]));
                        $archiveConnection->table($archiveTableName)->upsert(
                            $dataToArchive,
                            [$primaryId],
                            $columnsToUpdate,
                        );
                    }

                    if (!empty($idsToDelete)) {
                        $sourceConnection->table($sourceTableName)
                            ->whereIn($primaryId, $idsToDelete)
                            ->delete();
                    }
                }, $primaryId);
            return true;
        } catch (QueryException $e) {
            Logger::error($e->getMessage());
            return false;
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            return false;
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            return false;
        }
    }
}
