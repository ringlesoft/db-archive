<?php

namespace RingleSoft\DbArchive\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use RingleSoft\DbArchive\Services\TableArchiver;

class ArchiveTableJob implements ShouldQueue
{
    use Queueable, SerializesModels;


    protected String $table;
    protected array $settings;

    public function __construct( String $table, array $settings)
    {
        $this->table = $table;
        $this->settings = $settings;
    }

    public function handle(): void
    {
        TableArchiver::of($this->table)
        ->withSettings($this->settings)
        ->archive();
    }
}
