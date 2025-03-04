<?php

namespace RingleSoft\DbArchive\Services;

use Illuminate\Support\Facades\Config;

class ArchiveSettings
{
    public ?String $tablePrefix;
    public int $batchSize = 1000;
    public ?int $archiveOlderThanDays;
    public String $dateColumn = 'created_at';
    public bool $softDelete = false;
    public array $conditions = [];

    public function __construct(?int $archiveOlderThanDays, ?int $batchSize, ?String $dateColumn, ?bool $softDelete, ?String $tablePrefix, ?array $conditions)
    {
        $this->tablePrefix = $tablePrefix ?? null;
        $this->archiveOlderThanDays = $archiveOlderThanDays ?? 365;
        $this->batchSize = $batchSize ?? $this->batchSize;
        $this->dateColumn = $dateColumn ?? $this->dateColumn;
        $this->softDelete = $softDelete ?? $this->softDelete;
        $this->conditions = $conditions ?? $this->conditions;
    }

    public static function fromArray(array $settings): self
    {
        $defaultSettings = [
            'table_prefix' => Config::get('db_archive.backup.table_prefix'),
            'batch_size' => Config::get('db_archive.backup.batch_size', 1000),
            'archive_older_than_days' => Config::get('db_archive.backup.archive_older_than_days', 30),
            'date_column' => Config::get('db_archive.backup.date_column', 'created_at'),
            'soft_delete' => Config::get('db_archive.backup.soft_delete', false),
        ];
        $settings = array_merge($defaultSettings, $settings);
        return new self(
            archiveOlderThanDays: $settings['archive_older_than_days'] ?? null,
            batchSize: $settings['batch_size'] ?? null,
            dateColumn: $settings['date_column'] ?? null,
            softDelete: $settings['soft_delete'] ?? null,
            tablePrefix: $settings['table_prefix'] ?? null,
            conditions: $settings['conditions'] ?? null
        );
    }

}
