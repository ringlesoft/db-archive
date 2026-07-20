<?php

namespace RingleSoft\DbArchive\Services;

final class ArchiveResult
{
    public function __construct(
        public readonly string $table,
        public readonly string $archiveTable,
        public readonly int $scanned,
        public readonly int $archived,
        public readonly int $removed,
        public readonly int $softDeleted,
        public readonly bool $successful,
        public readonly ?string $error = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'archive_table' => $this->archiveTable,
            'scanned' => $this->scanned,
            'archived' => $this->archived,
            'removed' => $this->removed,
            'soft_deleted' => $this->softDeleted,
            'successful' => $this->successful,
            'error' => $this->error,
        ];
    }
}
