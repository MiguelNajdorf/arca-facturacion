<?php
declare(strict_types=1);
namespace ArcaFacturacion\Config;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Support\Cuit;
use ArcaFacturacion\Support\PathGuard;
final class ConfigLoader
{
    public function __construct(private readonly string $projectRoot) {}
    public function load(): ArcaConfig
    {
        $file = $this->projectRoot . '/.env';
        $values = is_readable($file) ? $this->parse((string) file_get_contents($file)) : [];
        $get = static function (string $key, mixed $default = null) use ($values): mixed {
            $environment = getenv($key);
            return $environment !== false ? $environment : ($values[$key] ?? $default);
        };
        foreach (['ARCA_CUIT', 'ARCA_POINT_OF_SALE', 'ARCA_CERT_PATH', 'ARCA_KEY_PATH'] as $key) {
            if (trim((string) $get($key, '')) === '') { throw new ConfigurationException("Falta {$key}. Ejecuta: " . \ArcaFacturacion\Support\DataDir::command() . " install"); }
        }
        return new ArcaConfig(
            strtolower((string) $get('ARCA_ENVIRONMENT', 'homologacion')),
            Cuit::normalizar((string) $get('ARCA_CUIT')),
            (int) $get('ARCA_POINT_OF_SALE'),
            PathGuard::absolute((string) $get('ARCA_CERT_PATH'), $this->projectRoot),
            PathGuard::absolute((string) $get('ARCA_KEY_PATH'), $this->projectRoot),
            PathGuard::absolute((string) $get('ARCA_STORAGE_PATH', 'storage'), $this->projectRoot),
            max(1, (int) $get('ARCA_CONNECTION_TIMEOUT', 20)),
            max(1, (int) $get('ARCA_REQUEST_TIMEOUT', 30)),
            strtolower((string) $get('ARCA_LOG_LEVEL', 'warning')),
            filter_var($get('ARCA_DEBUG', false), FILTER_VALIDATE_BOOL),
            (string) $get('ARCA_KEY_PASSPHRASE', ''),
            (string) $get('ARCA_WSAA_TESTING_URL', 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL'),
            (string) $get('ARCA_WSAA_PRODUCTION_URL', 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL'),
            (string) $get('ARCA_WSFE_TESTING_URL', 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL'),
            (string) $get('ARCA_WSFE_PRODUCTION_URL', 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL')
        );
    }
    /** @return array<string,string> */
    public function parse(string $content): array
    {
        $values = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) { continue; }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) { continue; }
            $value = trim($value);
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $decoded = json_decode($value, true);
                $value = is_string($decoded) ? $decoded : $value;
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }
        return $values;
    }
}
