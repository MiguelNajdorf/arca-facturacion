<?php
declare(strict_types=1);

namespace ArcaFacturacion\Certificate;

final class OpenSslConfiguration
{
    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    public function options(array $base): array
    {
        $config = $this->locate();
        if ($config !== null) { $base['config'] = $config; }
        return $base;
    }

    /**
     * Los argumentos permiten reproducir entornos web donde PHP_BINARY apunta
     * a Apache o FastCGI en vez del directorio de PHP.
     */
    public function locate(
        string|false|null $phpIni = null,
        ?string $phpBinary = null,
        string|false|null $extensionDirectory = null,
        string|false|null $environmentConfig = null
    ): ?string {
        $phpIni ??= php_ini_loaded_file();
        $phpBinary ??= PHP_BINARY;
        $extensionDirectory ??= ini_get('extension_dir');
        $environmentConfig ??= getenv('OPENSSL_CONF');

        $candidates = [];
        if (is_string($environmentConfig) && trim($environmentConfig) !== '') { $candidates[] = $environmentConfig; }
        if (is_string($phpIni) && $phpIni !== '') {
            $phpDirectory = dirname($phpIni);
            $candidates[] = $phpDirectory . '/extras/ssl/openssl.cnf';
            $candidates[] = $phpDirectory . '/openssl.cnf';
        }
        if ($phpBinary !== '') {
            $binaryDirectory = dirname($phpBinary);
            $candidates[] = $binaryDirectory . '/extras/ssl/openssl.cnf';
            $candidates[] = $binaryDirectory . '/openssl.cnf';
        }
        if (is_string($extensionDirectory) && trim($extensionDirectory) !== '') {
            $extensionDirectory = $this->absoluteExtensionDirectory($extensionDirectory, is_string($phpIni) ? dirname($phpIni) : null);
            $candidates[] = dirname($extensionDirectory) . '/extras/ssl/openssl.cnf';
            $candidates[] = dirname($extensionDirectory) . '/openssl.cnf';
        }
        $candidates[] = '/etc/ssl/openssl.cnf';
        $candidates[] = '/usr/local/etc/openssl/openssl.cnf';
        $candidates[] = '/opt/homebrew/etc/openssl@3/openssl.cnf';

        foreach (array_unique($candidates) as $candidate) {
            if (is_readable($candidate)) {
                $real = realpath($candidate);
                return $real !== false ? $real : $candidate;
            }
        }
        return null;
    }

    private function absoluteExtensionDirectory(string $directory, ?string $phpDirectory): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $directory) === 1 || $phpDirectory === null) { return $directory; }
        return $phpDirectory . '/' . ltrim($directory, '/\\');
    }
}
