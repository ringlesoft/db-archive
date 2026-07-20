<?php

namespace RingleSoft\DbArchive\Services;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RingleSoft\DbArchive\Events\TableArchived;
use RingleSoft\DbArchive\Events\TableArchivingFailed;
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
    private ?ArchiveResult $lastResult = null;

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
        return $this->archiveWithResult()->successful;
    }

    public function archiveWithResult(): ArchiveResult
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
        $softDelete = $this->settings->softDelete;
        $softDeleteColumn = $this->settings->softDeleteColumn;
        $scanned = 0;
        $archived = 0;
        $removed = 0;

        try {
            $sourceConnection->table($sourceTableName)
                ->where($dateColumn, '<', $this->cutoffDate)
                ->when($softDelete, fn ($query) => $query->whereNull($softDeleteColumn))
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
                ->chunkById($chunkSize, function ($sourceRecords) use ($sourceTableName, $archiveTableName, $archiveConnection, $sourceConnection, $primaryId, $softDelete, $softDeleteColumn, &$scanned, &$archived, &$removed) {
                    $dataToArchive = [];
                    $idsToDelete = [];

                    foreach ($sourceRecords as $record) {
                        $dataToArchive[] = (array)$record;
                        $idsToDelete[] = $record->{$primaryId};
                    }

                    $scanned += count($dataToArchive);

                    if (!empty($dataToArchive)) {
                        // Re-running a partially completed archive updates the same rows
                        // instead of creating duplicates before the source delete retries.
                        $columnsToUpdate = array_values(array_diff(array_keys($dataToArchive[0]), [$primaryId]));
                        $archiveConnection->table($archiveTableName)->upsert(
                            $dataToArchive,
                            [$primaryId],
                            $columnsToUpdate,
                        );
                        $archived += count($dataToArchive);
                    }

                    if (!empty($idsToDelete)) {
                        $sourceQuery = $sourceConnection->table($sourceTableName)
                            ->whereIn($primaryId, $idsToDelete)
                            ->when($softDelete, fn ($query) => $query->whereNull($softDeleteColumn));
                        $removed += $softDelete
                            ? $sourceQuery->update([$softDeleteColumn => Carbon::now()])
                            : $sourceQuery->delete();
                    }
                }, $primaryId);

            return $this->recordResult(new ArchiveResult(
                $sourceTableName,
                $archiveTableName,
                $scanned,
                $archived,
                $removed,
                $softDelete ? $removed : 0,
                true,
            ));
        } catch (Throwable $e) {
            return $this->recordResult(new ArchiveResult(
                $sourceTableName,
                $archiveTableName,
                $scanned,
                $archived,
                $removed,
                $softDelete ? $removed : 0,
                false,
                $e->getMessage(),
            ));
        }
    }

    public function lastResult(): ?ArchiveResult
    {
        return $this->lastResult;
    }

    private function recordResult(ArchiveResult $result): ArchiveResult
    {
        $this->lastResult = $result;
        Logger::log($result->successful ? 'info' : 'error', 'Database archive completed.', $result->toArray());

        try {
            Event::dispatch($result->successful ? new TableArchived($result) : new TableArchivingFailed($result));
        } catch (Throwable $eventException) {
            Logger::error('Could not dispatch database archive event.', ['exception' => $eventException]);
        }

        return $result;
    }
}
