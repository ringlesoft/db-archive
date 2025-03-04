<?php

namespace RingleSoft\DbArchive\Services;

use Doctrine\DBAL\Schema\Table;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Types;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

class SetupService
{
    public string $tablePrefix;
    public string $archiveConnection;
    public string $activeConnection;
    public function __construct()
    {
        $this->archiveConnection = Config::get('db_archive.connection');
        $this->tablePrefix = Config::get('db_archive.backup.table_prefix');
        $this->activeConnection = Config::get('database.connections')[Config::get('database.default')];
    }

    /**
     * Create a new table from the given schema.
     *
     */
    public function cloneTable($table): bool
    {
        // Get the schema manager
        $schemaManager = DB::connection($this->activeConnection)->getDoctrineSchemaManager();

        // Get the schema of the source table
        $sourceSchema = $schemaManager->listTableDetails($table);

        // Create a new table with the same schema

        $archiveTableName = $this->tablePrefix ? $this->tablePrefix . '_' . $table : $table;
        Schema::connection($this->archiveConnection)->create($archiveTableName, function ($table) use ($sourceSchema) {
            foreach ($sourceSchema->getColumns() as $column) {
                $type = $column->getType()->getName();
                $columnName = $column->getName();
                $length = $column->getLength();
                $precision = $column->getPrecision();
                $scale = $column->getScale();
                $unsigned = $column->getUnsigned();
                $notNull = $column->getNotnull();
                $default = $column->getDefault();
                $autoIncrement = $column->getAutoincrement();

                // Add the column to the new table
                $columnDefinition = $table->{$type}($columnName);
                if ($length) {
                    $columnDefinition->length($length);
                }
                if ($precision) {
                    $columnDefinition->precision($precision);
                }
                if ($scale) {
                    $columnDefinition->scale($scale);
                }
                if ($unsigned) {
                    $columnDefinition->unsigned();
                }
                if (!$notNull) {
                    $columnDefinition->nullable();
                }
                if ($default !== null) {
                    $columnDefinition->default($default);
                }

                if ($autoIncrement) {
                    $columnDefinition->autoIncrement();
                }
            }

            // Copy indexes
            foreach ($sourceSchema->getIndexes() as $index) {
                $columns = $index->getColumns();
                $indexName = $index->getName();
                $isUnique = $index->isUnique();
                $isPrimary = $index->isPrimary();
                if ($isPrimary) {
                    $table->primary($columns);
                } elseif ($isUnique) {
                    $table->unique($columns, $indexName);
                } else {
                    $table->index($columns, $indexName);
                }
            }

            // Copy foreign keys
            foreach ($sourceSchema->getForeignKeys() as $foreignKey) {
                $columns = $foreignKey->getLocalColumns();
                $foreignTable = $foreignKey->getForeignTableName();
                $foreignColumns = $foreignKey->getForeignColumns();
                $onDelete = $foreignKey->getOption('onDelete');
                $onUpdate = $foreignKey->getOption('onUpdate');
                $table->foreign($columns)
                    ->references($foreignColumns)
                    ->on($foreignTable)
                    ->onDelete($onDelete)
                    ->onUpdate($onUpdate);
            }
        });

        return true;
    }


    function cloneDatabase(): bool
    {
        $activeDatabaseName = Config::get("database.connections.$this->activeConnection.database");
        $archiveDatabaseName = Config::get("database.connections.$this->archiveConnection.database");
        // Get the connection configuration
        $archiveConfig = Config::get("database.connections.{$this->archiveConnection}");

        if (!$archiveConfig) {
            throw new InvalidArgumentException("Connection '$this->archiveConnection' not found in database configuration.");
        }

        try {
            // Connect to the source database
            $pdo = DB::connection($this->activeConnection)->getPdo();

            // Fetch the character set and collation of the source database
            switch ($archiveConfig['driver']) {
                case 'mysql':
                    $query = "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
                          FROM INFORMATION_SCHEMA.SCHEMATA
                          WHERE SCHEMA_NAME = ?";
                    break;
                case 'pgsql':
                    $query = "SELECT pg_encoding_to_char(encoding) AS encoding, datcollate
                          FROM pg_database
                          WHERE datname = ?";
                    break;
                case 'sqlsrv':
                    throw new RuntimeException("SQL Server does not support copying database settings like MySQL or PostgreSQL.");
                default:
                    throw new RuntimeException("Unsupported database driver: {$archiveConfig['driver']}");
            }

            $result = DB::connection($this->archiveConnection)->select($query, [$archiveDatabaseName]);

            if (empty($result)) {
                throw new RuntimeException("Source database '{$activeDatabaseName}' not found.");
            }

            $charset = $result[0]->DEFAULT_CHARACTER_SET_NAME ?? $result[0]->encoding ?? 'utf8mb4';
            $collation = $result[0]->DEFAULT_COLLATION_NAME ?? $result[0]->datcollate ?? 'utf8mb4_unicode_ci';


            // Create the new database with the same character set and collation
            return $this->createDatabase($this->archiveConnection, $archiveDatabaseName, $charset, $collation);

        } catch (PDOException $e) {
            throw new RuntimeException("Failed to create database '{$archiveDatabaseName}' based on '{$activeDatabaseName}': " . $e->getMessage());
        }
    }


    public function archiveDatabaseExists(): bool
    {
        $archiveConfig = Config::get("database.connections.{$this->archiveConnection}");
        $databaseName = $archiveConfig['database'];
        try {
            // Attempt to connect to the database server
            DB::connection($this->activeConnection)->getPdo();

            // Run a query to check if the database exists
            switch ($archiveConfig['driver']) {
                case 'mysql':
                    $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?";
                    break;
                case 'pgsql':
                    $query = "SELECT datname FROM pg_database WHERE datname = ?";
                    break;
                case 'sqlsrv':
                    $query = "SELECT name FROM sys.databases WHERE name = ?";
                    break;
                case 'sqlite':
                    return true;
                default:
                    throw new RuntimeException("Unsupported database driver: {$archiveConfig['driver']}");
            }
            $result = DB::connection($this->activeConnection)->select($query, [$databaseName]);
            return !empty($result);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function archiveTableExists(string $tableName): bool
    {
        $archiveConfig = Config::get("database.connections.{$this->archiveConnection}");
        $databaseName = $archiveConfig['database'];
        try {
            // Attempt to connect to the database server
            DB::connection($this->activeConnection)->getPdo();

            // Run a query to check if the table exists
            switch ($archiveConfig['driver']) {
                case 'mysql':
                    $query = "SHOW TABLES LIKE ?";
                    break;
                case 'pgsql':
                    $query = "SELECT tablename FROM pg_tables WHERE tablename = ?";
                    break;
                case 'sqlsrv':
                    $query = "SELECT name FROM sys.tables WHERE name = ?";
                    break;
                case 'sqlite':
                    return true;
                default:
                    throw new RuntimeException("Unsupported database driver: {$archiveConfig['driver']}");
            }
            $result = DB::connection($this->archiveConnection)->select($query, [$tableName]);
            return !empty($result);
        } catch (PDOException $e) {
            return false;
        }

    }





    function createDatabase($connectionName, $databaseName, $charset = 'utf8mb4', $collation = 'utf8mb4_unicode_ci'): bool
    {
        $config = Config::get("database.connections.{$connectionName}");
        if (!$config) {
            throw new \InvalidArgumentException("Connection '{$connectionName}' not found in database configuration.");
        }
        $originalDatabase = $config['database'];
        Config::set("database.connections.{$connectionName}.database", null);

        try {
            $pdo = DB::connection($connectionName)->getPdo();
            switch ($config['driver']) {
                case 'mysql':
                    $query = "CREATE DATABASE `{$databaseName}` CHARACTER SET {$charset} COLLATE {$collation}";
                    break;
                case 'pgsql':
                    $query = "CREATE DATABASE \"{$databaseName}\" ENCODING '{$charset}' LC_COLLATE '{$collation}'";
                    break;
                case 'sqlsrv':
                    $query = "CREATE DATABASE [{$databaseName}]";
                    break;
                default:
                    throw new \RuntimeException("Unsupported database driver: {$config['driver']}");
            }
            // Execute the query
            $pdo->exec($query);
            Config::set("database.connections.{$connectionName}.database", $originalDatabase);
            return true;
        } catch (PDOException $e) {
            Config::set("database.connections.{$connectionName}.database", $originalDatabase);
            throw new \RuntimeException("Failed to create database '{$databaseName}': " . $e->getMessage());
        }
    }
}
