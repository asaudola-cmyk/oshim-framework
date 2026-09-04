<?php
declare(strict_types=1);

namespace Oshim\Log;

use Oshim\Bootstrap;

/**
 * 👑 Sovereign Logger Engine
 * 
 * WHY: Replaces raw error_log() with structured, rotating, PSR-3 inspired logging.
 * Built for high-concurrency without deadlocks using atomic file locking (LOCK_EX).
 */
class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    protected static string $logPath = '';

    public static function setLogPath(string $path): void
    {
        self::$logPath = rtrim($path, '/');
    }

    protected static function getLogPath(): string
    {
        if (empty(self::$logPath)) {
            // Default to storage/logs
            self::$logPath = Bootstrap::getBasePath() . '/storage/logs';
        }
        
        // Edge Case: Create directory if it doesn't exist
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
        
        return self::$logPath;
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d');
        $file = self::getLogPath() . "/oshim-{$date}.log";
        
        $time = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        $levelUpper = strtoupper($level);
        $logEntry = "[{$time}] {$levelUpper}: {$message} {$contextStr}" . PHP_EOL;

        // Edge Case: Use FILE_APPEND | LOCK_EX for atomic writes during high concurrency
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function emergency(string $message, array $context = []): void { self::log(self::EMERGENCY, $message, $context); }
    public static function alert(string $message, array $context = []): void { self::log(self::ALERT, $message, $context); }
    public static function critical(string $message, array $context = []): void { self::log(self::CRITICAL, $message, $context); }
    public static function error(string $message, array $context = []): void { self::log(self::ERROR, $message, $context); }
    public static function warning(string $message, array $context = []): void { self::log(self::WARNING, $message, $context); }
    public static function notice(string $message, array $context = []): void { self::log(self::NOTICE, $message, $context); }
    public static function info(string $message, array $context = []): void { self::log(self::INFO, $message, $context); }
    public static function debug(string $message, array $context = []): void { self::log(self::DEBUG, $message, $context); }
}
