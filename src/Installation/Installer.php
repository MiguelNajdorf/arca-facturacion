<?php
declare(strict_types=1);
namespace ArcaFacturacion\Installation;
use ArcaFacturacion\Certificate\CertificateInfo;
use ArcaFacturacion\Certificate\CertificateInstaller;
use ArcaFacturacion\Certificate\CertificateValidator;
use ArcaFacturacion\Certificate\CsrGenerator;
use ArcaFacturacion\Config\ConfigLoader;
use ArcaFacturacion\Config\ConfigWriter;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Receipt\SQLiteReceiptRepository;
use ArcaFacturacion\Support\Cuit;
use ArcaFacturacion\Support\PathGuard;
final class Installer
{
    public const RECONFIGURE_CONFIRMATION = 'RECONFIGURAR_INSTALACION_EXISTENTE';
    private InstallationStateStore $state;
    public function __construct(private readonly string $root)
    {
        $this->state = new InstallationStateStore($root);
    }
    /**
     * @param array<string,mixed> $input
     * @return array<string,string>
     */
    public function configure(array $input, bool $generateCsr = true, ?string $overwriteConfirmation = null, bool $activate = true): array
    {
        $environment = strtolower((string) ($input['environment'] ?? 'homologacion'));
        if (!in_array($environment, ['homologacion', 'produccion'], true)) { throw new ConfigurationException('El ambiente debe ser homologacion o produccion.'); }
        $cuit = Cuit::normalizar((string) ($input['cuit'] ?? ''));
        if (!Cuit::esValido($cuit)) { throw new ConfigurationException('El CUIT no es valido. Escribi los 11 digitos; podes usar guiones.'); }
        $point = filter_var($input['point_of_sale'] ?? null, FILTER_VALIDATE_INT);
        if ($point === false || $point < 1 || $point > 99999) { throw new ConfigurationException('El punto de venta debe ser un numero entre 1 y 99999. Debe estar habilitado para Web Services en el ambiente elegido.'); }
        $alias = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($input['alias'] ?? 'arca')) ?? '', '-');
        if ($alias === '') { throw new ConfigurationException('El alias debe contener letras o numeros. No es una contrasena: usa un nombre descriptivo.'); }
        $confirmed = $overwriteConfirmation === self::RECONFIGURE_CONFIRMATION;
        if ($this->state->environment($environment) !== null && !$confirmed) {
            throw new ConfigurationException('Ya existe una configuracion para ' . $environment . '. Usa instalacion:continuar. Para reemplazarla se requiere confirmacion explicita.');
        }
        $storageRelative = (string) ($input['storage_path'] ?? 'storage');
        $storage = PathGuard::absolute($storageRelative, $this->root);
        $relativeDirectory = 'storage/certificados/' . $environment;
        $keyRelative = (string) ($input['key_path'] ?? $relativeDirectory . '/privada.key');
        $certRelative = (string) ($input['cert_path'] ?? $relativeDirectory . '/certificado.crt');
        $key = PathGuard::absolute($keyRelative, $this->root);
        $cert = PathGuard::absolute($certRelative, $this->root);
        $csr = dirname($key) . '/solicitud.csr';
        $profilePath = $this->root . '/storage/configuracion/' . $environment . '.env';
        $database = $storage . '/arca-' . $environment . '.sqlite';
        $writeTargets = [$profilePath, $database];
        if ($activate) { $writeTargets[] = $this->root . '/.env'; }
        if ($generateCsr) { $writeTargets[] = $key; $writeTargets[] = $csr; }
        $existingTargets = array_values(array_filter($writeTargets, 'file_exists'));
        if ($existingTargets !== [] && !$confirmed) {
            throw new ConfigurationException('No se modifico ningun archivo porque ya existen estos destinos: ' . implode(', ', $existingTargets) . '. Primero usa instalacion:estado; reemplazarlos requiere confirmacion explicita.');
        }
        if ($generateCsr) {
            $generated = (new CsrGenerator())->generate(dirname($key), $cuit, $alias, $confirmed ? CsrGenerator::REPLACE_CONFIRMATION : null);
            $key = $generated['key'];
            $csr = $generated['csr'];
        }
        $values = [
            'ARCA_ENVIRONMENT' => $environment,
            'ARCA_CUIT' => $cuit,
            'ARCA_POINT_OF_SALE' => $point,
            'ARCA_CERT_ALIAS' => $alias,
            'ARCA_CERT_PATH' => $certRelative,
            'ARCA_KEY_PATH' => $keyRelative,
            'ARCA_STORAGE_PATH' => $storageRelative,
            'ARCA_LOG_LEVEL' => (string) ($input['log_level'] ?? 'warning'),
            'ARCA_DEBUG' => false,
            'ARCA_CONNECTION_TIMEOUT' => (int) ($input['connection_timeout'] ?? 20),
            'ARCA_REQUEST_TIMEOUT' => (int) ($input['request_timeout'] ?? 30),
        ];
        $writer = new ConfigWriter($this->root);
        $configConfirmation = $confirmed ? ConfigWriter::REPLACE_CONFIRMATION : null;
        $profile = $writer->writeProfile($environment, $values, $configConfirmation);
        if ($activate) { $writer->write($values, $configConfirmation); }
        new SQLiteReceiptRepository($database);
        $this->state->saveEnvironment($environment, [
            'configured' => true,
            'cuit' => $cuit,
            'point_of_sale' => (int) $point,
            'alias' => $alias,
            'profile_path' => $profile,
            'key_path' => $key,
            'csr_path' => $csr,
            'certificate_path' => $cert,
            'database_path' => $database,
            'certificate_obtained' => false,
            'certificate_installed' => false,
            'service_authorized' => false,
            'connection_validated' => false,
            'simulation_completed' => false,
            'first_emission_completed' => false,
        ], $activate, false);
        return ['environment' => $environment, 'cuit' => $cuit, 'alias' => $alias, 'key' => $key, 'csr' => $csr, 'certificate' => $cert, 'database' => $database, 'profile' => $profile];
    }
    public function installCertificate(string $source, ?string $overwriteConfirmation = null): CertificateInfo
    {
        $config = (new ConfigLoader($this->root))->load();
        $info = (new CertificateInstaller(new CertificateValidator()))->install($source, $config->certificatePath, $config->privateKeyPath, $config->keyPassphrase, $overwriteConfirmation);
        if ($this->state->environment($config->environment) !== null) {
            $this->state->mark($config->environment, 'certificate_obtained');
            $this->state->mark($config->environment, 'certificate_installed');
        }
        return $info;
    }
    public function activateEnvironment(string $environment): void
    {
        if (!in_array($environment, ['homologacion', 'produccion'], true)) { throw new ConfigurationException('El ambiente debe ser homologacion o produccion.'); }
        $item = $this->state->environment($environment);
        $profile = is_array($item) ? (string) ($item['profile_path'] ?? '') : '';
        if ($profile === '' || !is_readable($profile)) { throw new ConfigurationException('No existe una configuracion preparada para ' . $environment . '.'); }
        $values = (new ConfigLoader($this->root))->parse((string) file_get_contents($profile));
        (new ConfigWriter($this->root))->write($values, ConfigWriter::REPLACE_CONFIRMATION);
        $this->state->activate($environment);
    }
    public function markOnlineValidated(): void
    {
        $environment = $this->state->activeEnvironment();
        if ($environment !== null) {
            $this->state->mark($environment, 'service_authorized');
            $this->state->mark($environment, 'connection_validated');
        }
    }
    public function markSimulation(): void
    {
        $environment = $this->state->activeEnvironment();
        if ($environment !== null) { $this->state->mark($environment, 'simulation_completed'); }
    }
    public function markFirstEmission(): void
    {
        $environment = $this->state->activeEnvironment();
        if ($environment !== null) { $this->state->mark($environment, 'first_emission_completed'); }
    }
    public function state(): InstallationStateStore { return $this->state; }
}
