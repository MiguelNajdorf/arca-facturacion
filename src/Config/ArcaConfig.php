<?php
declare(strict_types=1);
namespace ArcaFacturacion\Config;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Support\Cuit;
final class ArcaConfig
{
    public function __construct(
        public readonly string $environment,
        public readonly string $cuit,
        public readonly int $salesPoint,
        public readonly string $certificatePath,
        public readonly string $privateKeyPath,
        public readonly string $storagePath,
        public readonly int $connectionTimeout = 20,
        public readonly int $requestTimeout = 30,
        public readonly string $logLevel = 'warning',
        public readonly bool $debug = false,
        public readonly string $keyPassphrase = '',
        public readonly string $wsaaTestingUrl = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?WSDL',
        public readonly string $wsaaProductionUrl = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?WSDL',
        public readonly string $wsfeTestingUrl = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL',
        public readonly string $wsfeProductionUrl = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL'
    ) {
        if (!in_array($environment, ['homologacion', 'produccion'], true)) { throw new ConfigurationException('El ambiente debe ser homologacion o produccion.'); }
        if (!Cuit::esValido($cuit)) { throw new ConfigurationException('El CUIT configurado no es valido.'); }
        if ($salesPoint < 1 || $salesPoint > 99999) { throw new ConfigurationException('El punto de venta debe estar entre 1 y 99999.'); }
    }
    public function isProduction(): bool { return $this->environment === 'produccion'; }
    public function wsaaUrl(): string { return $this->isProduction() ? $this->wsaaProductionUrl : $this->wsaaTestingUrl; }
    public function wsfeUrl(): string { return $this->isProduction() ? $this->wsfeProductionUrl : $this->wsfeTestingUrl; }
    public function databasePath(): string { return $this->storagePath . '/arca-' . $this->environment . '.sqlite'; }
    public function ticketsPath(): string { return $this->storagePath . '/tickets/' . $this->environment; }
    public function locksPath(): string { return $this->storagePath . '/locks/' . $this->environment; }
    public function logsPath(): string { return $this->storagePath . '/logs/' . $this->environment; }
}
