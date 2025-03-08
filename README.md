# Laravel DB Archive

Easily archive your Laravel database tables periodically to keep your application database lean and performant.
***
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ringlesoft/db-archive.svg)](https://packagist.org/packages/ringlesoft/db-archive)
[![Total Downloads](https://img.shields.io/packagist/dt/ringlesoft/db-archive.svg)](https://packagist.org/packages/ringlesoft/db-archive)
[![PHP Version Require](https://poser.pugx.org/ringlesoft/db-archive/require/php)](https://packagist.org/ringlesoft/db-archive)
[![Dependents](https://poser.pugx.org/ringlesoft/db-archive/dependents)](https://packagist.org/packages/ringlesoft/db-archive)
***
## Introduction

`Laravel DB Archive` is a package that provides a simple and efficient way to archive old records from your database tables in Laravel applications.  It helps maintain your application's database performance by moving historical data to archive tables, while keeping your primary tables focused on recent and relevant information.

> Laravel 10.x and above

## Installation
You can install the package via composer:

```bash
composer require ringlesoft/db-archive
```

## Configuration
Publish the configuration file with:

```bash
php artisan vendor:publish --provider="RingleSoft\DbArchive\DbArchiveServiceProvider" --tag="config"
```

### Configuration Options:
#### `connection`:
- The database connection name to be used for creating archive tables and moving data.
- Ensure this connection is defined in your config/database.php file.
- Defaults to mysql_archive and can be overridden using the ARCHIVE_DB_CONNECTION environment variable.

#### `settings`:
- `table_prefix`:
  - Prefix to be added to the archived tables (e.g., archive_).
  - Set to `null` for no prefix.

- `batch_size`:
  - Number of records to process in each batch during archiving.
  - Adjust this value based on your server resources and table size.
  - Defaults to 1000.

- `archive_older_than_days`:
  - Number of days after which records are considered old enough to be archived.
  - Records with a date in the date_column older than this value will be archived.
  - Defaults to 30 days .

- `date_column`:
  - The database column used to determine the age of records for archiving (e.g., created_at, updated_at).
  - Defaults to created_at.

- `soft_delete`:
  - Boolean value to indicate if soft-deleted records should be included in the archive.
  - If set to true, records that are soft-deleted (have a deleted_at timestamp) will also be archived if they meet the age criteria.
  - Defaults to false.

- `conditions`:
  - An array of additional where conditions to filter records for archiving.
  - Allows for more specific criteria for selecting records to archive.
  - Defaults to an empty array `[]`.

#### `enable_logging`:
- Boolean value to enable or disable logging of the archiving process.
- Logs are stored in the default Laravel log file.
- Defaults to true.

#### `notifications`:
- email:
  - Email address to receive notifications about the archiving process (success or failure).
  - Set to null to disable email notifications.
  - Defaults to admin@example.com.
  
#### `tables`:
- An array defining the tables to be archived.
- Plain Table Names: Specify table names as strings to use the default settings for archiving.

## Setting Up
To setup the package, run the following command:
```bash
php artisan db-archive:setup
```
This will create the backup database and tables if not already present.


## Queueing and Batching
- This package supports job queuing and job batches. 
- To use jobs and batches, make sure you have both jobs and batches enabled in your application.

### Jobs Table
```bash
php artisan queue:table
php artisan migrate
```

### Batches table
```bash
php artisan queue:batches-table
php artisan migrate
```


## Usage
### Artisan Command
Run the archive command to process tables defined in your configuration:
```bash
php artisan db:archive
```
This command will:
- **Check Configuration**: Load the db-archive.php configuration file.
- **Process Tables**: Iterate through the tables defined in the tables array.
- **Create Archive** Tables (if needed): For each table, it checks if an archive table (with the configured prefix, if any) exists. If not, it creates a new archive table with the same schema as the original table.
- **Archive Records**: Move records from the original table to the archive table based on the configured settings (date column, age, conditions, etc.).
- **Logging and Notifications**: Log the archiving process and send notifications if enabled.

### Scheduling
To automate the archiving process, schedule the db:archive command in your Kernel.php file:

// app/Console/Kernel.php
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('db:archive')->dailyAt('01:00'); // Run daily at 1:00 AM
}

```
Adjust the scheduling as per your requirements (e.g., daily, weekly, monthly).
    
## Examples

### Basic Usage 
(Archive orders table with default settings)
> // config/db-archive.php
```php
'tables' => [
    'orders',
],
```
This configuration will archive records from the users table that are older than 30 days (based on the created_at column) to a table named archive_users (if table_prefix is set to archive_).

## Custom Settings


> // config/db-archive.php

```php
'tables' => [
    'orders' => [
        'archive_older_than_days' => 90, // Archive orders older than 90 days
        'date_column' => 'order_date',   // Use 'order_date' column for age check
        'batch_size' => 5000,            // Process in batches of 5000
        'conditions' => [                 // Additional conditions
            ['status', '=', 'completed'],
        ],
    ],
];
```
This configuration will archive records from the orders table that are older than 90 days (based on the order_date column), processed in batches of 5000, and only for orders with a status of 'completed'.


## Contributing
Contributions are welcome! Please feel free to submit pull requests or open issues to suggest improvements or report bugs.

## License
The Laravel DB Archive package is open-sourced software licensed under the MIT license.


## Support
- [Buy me a Coffee](https://www.buymeacoffee.com/ringunger)
- [Github Sponsors](https://github.com/sponsors/ringlesoft)

## Contacts

Follow me on <a href="https://x.com/ringunger">X</a>: <a href="https://x.com/ringunger">@ringunger</a><br>
Email me: <a href="mailto:ringunger@gmail.com">ringunger@gmail.com</a><br>
Website: [https://ringlesoft.com](https://ringlesoft.com/packages/db-archive)
