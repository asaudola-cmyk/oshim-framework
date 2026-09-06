<?php

declare(strict_types=1);

/**
 * 👑 UNUM Autoloader & Bootstrap Gateway
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'Unum\\';
    $baseDir = __DIR__ . '/Unum/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
