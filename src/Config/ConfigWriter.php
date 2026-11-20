<?php
declare(strict_types=1);
namespace ArcaFacturacion\Config;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Support\AtomicFile;
final class ConfigWriter
{
    public const REPLACE_CONFIRMATION = 'REEMPLAZAR_CONFIGURACION_EXISTENTE';
    public function __construct(private readonly string $projectRoot) {}
    /** @param array<string,scalar> $values */
    public function write(array $values, ?string $overwriteConfirmation = null): void
    {
        $this->writeTo($this->projectRoot . '/.env', $values, $overwriteConfirmation);
    }
    /** @param array<string,scalar> $values */
    public function writeProfile(string $environment, array $values, ?string $overwriteConfirmation = null): string
    {
        $path = $this->projectRoot . '/storage/configuracion/' . $environment . '.env';
        $this->writeTo($path, $values, $overwriteConfirmation);
        return $path;
    }
    /** @param array<string,scalar> $values */
    public function writeTo(string $path, array $values, ?string $overwriteConfirmation = null): void
    {
        $lines = ['# Generado por arca_facturacion. No subir este archivo al repositorio.'];
        foreach ($values as $key => $value) {
            if (!preg_match('/^ARCA_[A-Z0-9_]+$/', $key)) { continue; }
            $string = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            if (preg_match('/\s|#|=/', $string)) {
                $string = json_encode($string, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            $lines[] = $key . '=' . $string;
        }
        $content = implode(PHP_EOL, $lines) . PHP_EOL;
        if (file_exists($path)) {
            if ($overwriteConfirmation !== self::REPLACE_CONFIRMATION) { throw new ConfigurationException('El archivo ya existe y no sera reemplazado sin confirmacion explicita: ' . $path); }
            AtomicFile::write($path, $content, 0600);
            return;
        }
        AtomicFile::writeNew($path, $content, 0600);
    }
}
