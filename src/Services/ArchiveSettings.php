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

    public function __construct(int $archiveOlderThanDays, ?int $batchSize, ?String $dateColumn, ?bool $softDelete, ?String $tablePrefix)
    {
        $this->tablePrefix = $tablePrefix;
        $this->archiveOlderThanDays = $archiveOlderThanDays;
        $this->batchSize = $batchSize ?? $this->batchSize;
        $this->dateColumn = $dateColumn ?? $this->dateColumn;
        $this->softDelete = $softDelete ?? $this->softDelete;
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
            batchSize: $settings['batch_size'],
            dateColumn: $settings['date_column'],
            softDelete: $settings['soft_delete'],
            tablePrefix: $settings['table_prefix']
        );
    }

}
