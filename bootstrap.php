<?php
declare(strict_types=1);
$composer = __DIR__ . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'ArcaFacturacion\\';
        if (!str_starts_with($class, $prefix)) { return; }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) { require_once $path; }
    });
}
date_default_timezone_set(getenv('ARCA_TIMEZONE') ?: 'America/Argentina/Buenos_Aires');
