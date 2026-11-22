<?php
declare(strict_types=1);
// Autoload de Composer: el del proyecto que nos instalo (vendor/bin/arca lo informa), el propio si se clono aparte,
// o el del vendor/ que nos contiene.
$composer = array_values(array_filter([
    $GLOBALS['_composer_autoload_path'] ?? null,
    __DIR__ . '/vendor/autoload.php',
    dirname(__DIR__, 2) . '/autoload.php',
], static fn (?string $path): bool => $path !== null && is_file($path)))[0] ?? null;
if ($composer !== null) {
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
