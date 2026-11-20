<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
use ArcaFacturacion\Exception\StorageException;
final class AtomicFile
{
    public static function writeNew(string $path, string $content, int $permissions = 0600): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new StorageException('No se pudo crear la carpeta de almacenamiento.'); }
        $handle = @fopen($path, 'x+b');
        if ($handle === false) { throw new StorageException('El archivo ya existe y no sera reemplazado sin confirmacion explicita: ' . $path); }
        try {
            if (!flock($handle, LOCK_EX) || fwrite($handle, $content) !== strlen($content) || !fflush($handle)) { throw new StorageException('No se pudo escribir el archivo nuevo de forma segura.'); }
            @chmod($path, $permissions);
        } catch (\Throwable $error) {
            fclose($handle); @unlink($path); throw $error;
        }
        flock($handle, LOCK_UN); fclose($handle);
    }
    public static function write(string $path, string $content, int $permissions = 0600): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new StorageException('No se pudo crear la carpeta de almacenamiento.'); }
        $temporary = tempnam($directory, '.arca-');
        if ($temporary === false || file_put_contents($temporary, $content, LOCK_EX) === false) { throw new StorageException('No se pudo escribir el archivo de forma segura.'); }
        @chmod($temporary, $permissions);
        if (!rename($temporary, $path)) { @unlink($temporary); throw new StorageException('No se pudo confirmar el archivo escrito.'); }
    }
}
