<?php

namespace RingleSoft\DbArchive\Utility;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Stringable;

class Logger extends Log
{
    public static function emergency(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::emergency($message, $context);
        }
    }

    public static function alert(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::alert($message, $context);
        }
    }

    public static function critical(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::critical($message, $context);
        }
    }

    public static function error(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::error($message, $context);
        }
    }

    public static function warning(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::warning($message, $context);
        }
    }

    public static function notice(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::notice($message, $context);
        }
    }

    public static function info(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::info($message, $context);
        }
    }

    public static function debug(string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::debug($message, $context);
        }
    }

    public static function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if(self::loggingEnabled()){
            parent::log($message, $context);
        }
    }

    public static function loggingEnabled(): bool
    {
        return (bool) Config::get('db_archive.enable_logging', false);
    }
}
