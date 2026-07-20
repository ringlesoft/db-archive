<?php

namespace RingleSoft\DbArchive\Jobs;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use RingleSoft\DbArchive\Services\TableArchiver;
use RuntimeException;
use Throwable;

class ArchiveTableJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue, Dispatchable, Batchable;


    protected String $table;
    protected array $settings;

    public function __construct( String $table, array $settings)
    {
        $this->table = $table;
        $this->settings = $settings;
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $archived = TableArchiver::of($this->table)
            ->withSettings($this->settings)
            ->archive();

        if (!$archived) {
            throw new RuntimeException("Failed to archive table '{$this->table}'.");
        }
    }
}
