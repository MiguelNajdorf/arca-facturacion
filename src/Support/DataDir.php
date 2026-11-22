<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
use ArcaFacturacion\Exception\StorageException;

/**
 * Carpeta donde viven .env y storage/ (configuracion, clave privada, certificados y SQLite).
 *
 * - ARCA_DIR, si esta definida.
 * - <proyecto>/arca, si la libreria se instalo con Composer: asi los datos no quedan en vendor/ y sobreviven a composer update.
 * - La carpeta de la libreria, si se clono aparte.
 */
final class DataDir
{
    public static function resolve(): string
    {
        $configured = getenv('ARCA_DIR');
        if (is_string($configured) && trim($configured) !== '') { return rtrim(trim($configured), '/\\'); }
        return self::isDependency() ? dirname(self::package(), 3) . DIRECTORY_SEPARATOR . 'arca' : self::package();
    }

    /** Comando para invocar la CLI, tal como hay que escribirlo en la terminal. */
    public static function command(): string
    {
        return self::isDependency() ? 'php vendor/bin/arca' : 'php bin/arca';
    }

    /** Crea la carpeta de datos con protecciones contra git y Apache. No toca una carpeta existente. */
    public static function prepare(string $dir): void
    {
        if (is_dir($dir) || $dir === self::package()) { return; }
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) { throw new StorageException("No se pudo crear la carpeta de datos {$dir}."); }
        // La clave privada nunca tiene que llegar a un repositorio ni ser descargable si la carpeta queda bajo la raiz web.
        file_put_contents($dir . '/.gitignore', "# Datos privados de arca-facturacion: clave, certificados y facturas.\n*\n");
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }

    private static function package(): string
    {
        return dirname(__DIR__, 2);
    }

    /** shortcut: asume el vendor-dir por defecto de Composer ("vendor"); con otro nombre, definir ARCA_DIR. */
    private static function isDependency(): bool
    {
        return basename(dirname(self::package(), 2)) === 'vendor';
    }
}
