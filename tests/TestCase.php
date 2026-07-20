<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RingleSoft\DbArchive\DbArchiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DbArchiveServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $sqliteConnection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];

        $app['config']->set('database.default', 'source');
        $app['config']->set('database.connections.source', $sqliteConnection);
        $app['config']->set('database.connections.archive', $sqliteConnection);
        $app['config']->set('db_archive.connection', 'archive');
        $app['config']->set('db_archive.enable_logging', false);
        $app['config']->set('db_archive.settings.table_prefix', 'archive');
        $app['config']->set('db_archive.settings.batch_size', 2);
        $app['config']->set('db_archive.settings.archive_older_than_days', 30);
        $app['config']->set('db_archive.settings.date_column', 'created_at');
        $app['config']->set('db_archive.settings.primary_id', 'id');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('source')->create('records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::connection('archive')->create('archive_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
}
