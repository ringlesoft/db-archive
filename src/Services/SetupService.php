<?php

namespace RingleSoft\DbArchive\Services;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;


class SetupService
{
    public ?string $tablePrefix;
    public string $archiveConnection;
    public string $activeConnection;


    public function __construct()
    {
        $this->archiveConnection = Config::get('db_archive.connection');
        $this->tablePrefix = Config::get('db_archive.settings.table_prefix');
        $this->activeConnection = Config::get("database.default");
    }

    /**
     * Create a new table from the given schema.
     * @param $tableName
     * @return bool
     * @throws Exception
     */
    public function cloneTable($tableName): bool
    {
        $targetTableName = $this->tablePrefix ? ("{$this->tablePrefix}_{$tableName}") : $tableName;
        $sourceConnectionName = $this->activeConnection;
        $archiveConnectionName = $this->archiveConnection;

        $sourceConnection = DB::connection($sourceConnectionName);
        $archiveConnection = DB::connection($archiveConnectionName);
        $driverName = $sourceConnection->getDriverName();
        $createTableStatement = '';
        try {
            switch ($driverName) {
                case 'mysql':
                    $createTableSql = $sourceConnection
                        ->select("SHOW CREATE TABLE `{$tableName}`");
                    if (empty($createTableSql)) {
                        throw new RuntimeException("Source table '$tableName' does not exist on connection '$sourceConnectionName'.");
                    }
                    $createTableStatement = $createTableSql[0]->{'Create Table'};
                    break;
                case 'pgsql':
                    $createTableSql = $sourceConnection
                        ->select("SELECT pg_get_ddl('table', '$tableName') AS create_statement");
                    if (empty($createTableSql)) {
                        throw new RuntimeException("Source table '$tableName' does not exist on connection '$sourceConnectionName'.");
                    }
                    $createTableStatement = $createTableSql[0]->create_statement;
                    break;
                case 'sqlite':
                    $createTableSql = $sourceConnection
                        ->select("SELECT sql FROM sqlite_master WHERE type='table' AND name='$tableName'");
                    if (empty($createTableSql)) {
                        throw new RuntimeException("Source table '{$tableName}' does not exist on connection '$sourceConnectionName'.");
                    }
                    $createTableStatement = $createTableSql[0]->sql;
                    break;
                case 'sqlsrv':
                    $createTableSql = $sourceConnection
                        ->select("sp_helptext '{$tableName}'");
                    if (empty($createTableSql)) {
                        throw new RuntimeException("Source table '$tableName' does not exist on connection '$sourceConnectionName'.");
                    }
                    $createTableStatementArray = array_column($createTableSql, 'Text');
                    $createTableStatement = implode('', $createTableStatementArray);
                    $createTableStatement = str_replace(array("\r", "\n", "\t"), '', $createTableStatement);
                    if (stripos($createTableStatement, 'CREATE TABLE') !== false) {
                        $createTableStatement = substr($createTableStatement, stripos($createTableStatement, 'CREATE TABLE'));
                    } else {
                        throw new RuntimeException("Could not extract CREATE TABLE statement from sp_helptext output for table '$tableName' on connection '$sourceConnectionName'.");
                    }
                    break;
                default:
                    throw new RuntimeException("Database driver '{$driverName}' is not supported for table cloning.");
            }

            // Modify the CREATE TABLE statement to use the target table name
            $createTableStatement = str_replace("`$tableName`", "`$targetTableName`", $createTableStatement);
            if ($driverName === 'pgsql') {
                $createTableStatement = str_replace((string)$tableName, (string)$targetTableName, $createTableStatement); //For postgresql, table name might not be quoted in CREATE TABLE statement
            } else if ($driverName === 'sqlsrv') {
                // For SQL Server, table names might be quoted with brackets
                $createTableStatement = str_replace(array("[$tableName]", (string)$tableName), array("[$targetTableName]", (string)$targetTableName), $createTableStatement); // For SQL Server, table names might not be quoted in CREATE TABLE statement
            }
            // Execute the modified create table statement on the target connection
            $archiveConnection->statement($createTableStatement);
            return true;
        } catch (Exception $e) {
            if ($e instanceof QueryException && stripos($e->getMessage(), 'already exists')) {
                // Table already exists in the archive connection, consider it a success.
                return true;
            }
            throw $e;
        }
    }


    /**
     * Clone the database structure from the active connection to the archive connection.
     * @return bool
     * @throws Exception
     */
    public function cloneDatabase(): bool
    {
        $sourceConnection = DB::connection($this->activeConnection);
        $targetConnection = DB::connection($this->archiveConnection);
        $sourceDatabaseName = $sourceConnection->getDatabaseName() ?? '';
        $targetDatabaseName = $targetConnection->getDatabaseName() ?? '';
        $sourceDriverName = $sourceConnection->getDriverName();
        $targetDriverName = $targetConnection->getDriverName();
        $sourceConnectionName = $this->activeConnection;
        $targetConnectionName = $this->archiveConnection;

        // Check if source and target drivers are the same for simplicity.
        // For cross-database cloning, more complex logic would be needed to handle schema differences.
        if ($sourceDriverName !== $targetDriverName) {
            throw new RuntimeException("Source and target database drivers must be the the same for simple database cloning. Source: '$sourceDriverName', Target: '$targetDriverName'.");
        }

        if (empty($sourceDatabaseName)) {
            throw new RuntimeException("Source database name is not set for connection '$sourceConnectionName'.");
        }
        if (empty($targetDatabaseName)) {
            throw new RuntimeException("Target database name must be specified.");
        }

        try {
            switch ($sourceDriverName) {
                case 'mysql':
                    $createDatabaseStatement = "CREATE DATABASE `$targetDatabaseName`";
                    break;
                case 'pgsql':
                    $createDatabaseStatement = "CREATE DATABASE \"$targetDatabaseName\"";
                    break;
                case 'sqlite':
                    $databasePath = config("database.connections.$targetConnectionName.database");
                    if (file_exists($databasePath)) {
                        unlink($databasePath); // Delete existing file to ensure empty DB
                    }
                    DB::purge($targetConnectionName);
                    DB::reconnect($targetConnectionName);
                    return true;
                case 'sqlsrv':
                    $createDatabaseStatement = "CREATE DATABASE {$targetDatabaseName}";
                    break;
                default:
                    throw new RuntimeException("Database driver '$sourceDriverName' is not supported for database cloning.");
            }
            if ($sourceDriverName !== 'sqlite') {
                DB::statement($createDatabaseStatement);
            }
            return true;
        } catch (Exception $e) {
            if ($e instanceof QueryException && stripos($e->getMessage(), 'already exists')) {
                // Database already exists in the target connection, consider it a success.
                return true;
            }
            throw $e;
        }
    }


    public function archiveDatabaseExists(): bool
    {
        $connection = DB::connection($this->archiveConnection);
        $connection->reconnect();
        $driverName = $connection->getDriverName();
        $databaseName = $connection->getDatabaseName();
        try {
            switch ($driverName) {
                case 'mysql':
                    $databases = $connection->select("SHOW DATABASES LIKE '$databaseName'");
                    return !empty($databases);
                case 'pgsql':
                    $databases = $connection->select("SELECT 1 FROM pg_database WHERE datname = '$databaseName'");
                    return !empty($databases);
                case 'sqlite':
                    // SQLite is file-based, checking if the database file exists is sufficient.
                    $databasePath = config("database.connections.$this->archiveConnection.database");
                    return file_exists($databasePath);
                case 'sqlsrv':
                    $databases = $connection->select("SELECT database_id FROM sys.databases WHERE name = '$databaseName'");
                    return !empty($databases);
                default:
                    throw new RuntimeException("Database driver '$driverName' is not supported for checking database existence.");
            }
        } catch (QueryException $e) {
            // Handle potential connection errors or other database-related issues
            // For example, if the connection itself is invalid.
            report($e); // Optionally log the exception
            return false; // Assume database doesn't exist if there's an error reaching the database server.
        } catch (Exception $e) {
            report($e); // Log unexpected exceptions
            throw $e; // Re-throw unexpected exceptions
        }
    }

    public function archiveTableExists(string $tableName): bool
    {
        $archiveConfig = Config::get("database.connections.$this->archiveConnection");
        $databaseName = $archiveConfig['database'];
        try {
            // Attempt to connect to the database server
            DB::connection($this->activeConnection)->getPdo();


            // Run a query to check if the table exists
            switch ($archiveConfig['driver']) {
                case 'mysql':
                    $query = "SHOW TABLES LIKE '$tableName'";
                    break;
                case 'pgsql':
                    $query = "SELECT tablename FROM pg_tables WHERE tablename =  '$tableName'";
                    break;
                case 'sqlsrv':
                    $query = "SELECT name FROM sys.tables WHERE name =  '$tableName'";
                    break;
                case 'sqlite':
                    return true;
                default:
                    throw new RuntimeException("Unsupported database driver: {$archiveConfig['driver']}");
            }
            $result = DB::connection($this->archiveConnection)->select($query);
            return !empty($result);
        } catch (PDOException $e) {
            return false;
        }

    }


    function createDatabase($connectionName, $databaseName, $charset = 'utf8mb4', $collation = 'utf8mb4_unicode_ci'): bool
    {
        $config = Config::get("database.connections.{$connectionName}");
        if (!$config) {
            throw new InvalidArgumentException("Connection '{$connectionName}' not found in database configuration.");
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
                    throw new RuntimeException("Unsupported database driver: {$config['driver']}");
            }
            // Execute the query
            $pdo->exec($query);
            Config::set("database.connections.{$connectionName}.database", $originalDatabase);
            return true;
        } catch (PDOException $e) {
            Config::set("database.connections.{$connectionName}.database", $originalDatabase);
            throw new RuntimeException("Failed to create database '{$databaseName}': " . $e->getMessage());
        }
    }

}
