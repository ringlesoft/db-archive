<?php
namespace RingleSoft\DbArchive;

use Illuminate\Support\ServiceProvider;
use RingleSoft\DbArchive\Console\Commands\ArchiveDataCommand;
use RingleSoft\DbArchive\Console\Commands\BatchStatusCommand;
use RingleSoft\DbArchive\Console\Commands\SetupCommand;

class DbArchiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->alias(Facades\DbArchive::class, 'DbArchive');
    }

    public function boot(): void
    {

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishItems();

        $this->mergeConfigFrom(
            __DIR__ . '/../config/db_archive.php', 'db_archive'
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ArchiveDataCommand::class,
                BatchStatusCommand::class,
                SetupCommand::class,
            ]);
        }
    }

    private function publishItems(): void
    {
        if (!function_exists('config_path') || !$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/db_archive.php' => config_path('db_archive.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }
}
