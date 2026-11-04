<?php

declare(strict_types=1);

/*
 * PHPStan bootstrap for the PHPStan engine (ADR-0007): autoload only this
 * extension namespace, so a standalone consult-rector install never pulls its
 * own Composer dependencies into the project's PHPStan process.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'TypedDuck\\ConsultRector\\PhpStan\\Extension\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
