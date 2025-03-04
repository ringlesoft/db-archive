# DB Archive

### Supported Databases
- MySQL
- PostgreSQL
- SQL Server
- SQLite (Only archiving within the same database)

### Installation

You can install the package via composer:

```bash
composer require ringlesoft/db-archive
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="RingleSoft\DbArchive\DbArchiveServiceProvider"
```

### Configuration
