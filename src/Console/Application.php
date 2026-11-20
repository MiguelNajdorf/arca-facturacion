<?php
declare(strict_types=1);
namespace ArcaFacturacion\Console;
use ArcaFacturacion\Arca;
use ArcaFacturacion\Certificate\CsrGenerator;
use ArcaFacturacion\Config\ConfigLoader;
use ArcaFacturacion\Diagnostics\Doctor;
use ArcaFacturacion\Exception\ArcaFacturacionException;
use ArcaFacturacion\Exception\CertificateException;
use ArcaFacturacion\Installation\InstallationGuide;
use ArcaFacturacion\Installation\InstallationStateStore;
use ArcaFacturacion\Installation\Installer;
use Throwable;
final class Application
{
    public function __construct(private readonly string $root) {}
    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $options = $this->options(array_slice($argv, 2));
        try {
            return match ($command) {
                'help', '--help', '-h' => $this->help(),
                'install' => $this->install($options),
                'instalacion:estado' => $this->installationStatus($options),
                'instalacion:continuar' => $this->continueInstallation($options),
                'produccion:preparar' => $this->prepareProduction($options),
                'ambiente:cambiar' => $this->changeEnvironment($options),
                'doctor' => $this->doctor(isset($options['online']), isset($options['json'])),
                'certificado:generar' => $this->generateCertificate($options),
                'certificado:instalar' => $this->installCertificate($options),
                'factura:simular' => $this->simulate($options),
                'factura:probar' => $this->testInvoice($options),
                'operaciones:listar' => $this->listOperations($options),
                'operaciones:recuperar' => $this->recover($options),
                default => $this->unknown($command),
            };
        } catch (ArcaFacturacionException $error) {
            fwrite(STDERR, '[ERROR] ' . $error->mensajeHumano() . PHP_EOL);
            fwrite(STDERR, "Siguiente paso sugerido: php bin/arca instalacion:estado\n");
            if (isset($options['debug']) && $error->detalleTecnico()) { fwrite(STDERR, '[DETALLE] ' . $error->detalleTecnico() . PHP_EOL); }
            return 2;
        } catch (Throwable $error) {
            fwrite(STDERR, "[ERROR] Ocurrio un error inesperado. Ejecuta doctor y revisa instalacion:estado.\n");
            if (isset($options['debug'])) { fwrite(STDERR, $error->getMessage() . PHP_EOL); }
            return 2;
        }
    }
    private function help(): int
    {
        echo "arca_facturacion - Facturacion electronica ARCA guiada para PHP\n\n";
        echo "Uso: php bin/arca <comando> [opciones]\n\n";
        echo "  install                         Inicia o retoma la instalacion guiada\n";
        echo "  instalacion:estado              Muestra lo completado y el proximo paso\n";
        echo "  instalacion:continuar           Retoma automaticamente el paso pendiente\n";
        echo "  produccion:preparar             Prepara produccion sin tocar homologacion\n";
        echo "  ambiente:cambiar AMBIENTE       Activa un ambiente ya preparado\n";
        echo "  doctor [--online]               Diagnostica entorno, configuracion y ARCA\n";
        echo "  certificado:generar             Genera clave y CSR con proteccion anti-sobrescritura\n";
        echo "  certificado:instalar RUTA       Valida e instala el certificado descargado\n";
        echo "  factura:simular --total=N       Valida una Factura C sin enviarla\n";
        echo "  factura:probar --total=N        Confirma y emite en el ambiente activo\n";
        echo "  operaciones:listar              Muestra operaciones recientes\n";
        echo "  operaciones:recuperar ID        Reconcilia una operacion incierta\n";
        return 0;
    }
    /** @param array<string,string|bool> $options */
    private function install(array $options): int
    {
        $installer = new Installer($this->root);
        $stateStore = $installer->state();
        $requested = isset($options['environment']) ? $this->normalizeEnvironment((string) $options['environment']) : $stateStore->activeEnvironment();
        $existing = $stateStore->environment($requested);
        if ($existing !== null && !isset($options['reconfigure'])) {
            $this->banner('Instalacion encontrada');
            $this->resumeSummary($existing, $stateStore);
            if ($this->interactive() && strtolower($this->ask('Queres continuar desde este punto? [S/n]', 's')) === 'n') { return $this->showProgress($stateStore, (string) $existing['environment']); }
            return $this->showCurrentStep($existing, $stateStore);
        }
        $this->banner('Asistente de instalacion');
        echo "Vamos a preparar la integracion paso a paso. Podes cerrar la consola en cualquier pausa\ny retomar con: php bin/arca instalacion:continuar\n\n";
        $this->showEnvironmentChecks();
        $guide = new InstallationGuide();
        echo "\n" . $guide->environmentExplanation() . "\n\n";
        $interactive = $this->interactive();
        $environment = $this->normalizeEnvironment((string) ($options['environment'] ?? ($interactive ? $this->ask('Ambiente [homologacion/produccion]', 'homologacion') : 'homologacion')));
        echo "\nPASO 2 DE 6 - Guardar la configuracion y crear la solicitud\n";
        echo "En este paso se guardan el ambiente, el CUIT, el punto de venta y el alias.\nDespues se crean la clave privada y el CSR en rutas separadas.\n\n";
        $previous = $stateStore->environment($environment);
        $reconfigure = $previous !== null;
        if ($reconfigure) {
            $confirmed = isset($options['yes']) || ($interactive && $this->ask('Esto reemplaza la configuracion y el par de claves. Escribi RECONFIGURAR', '') === 'RECONFIGURAR');
            if (!$confirmed) { echo "No se modifico ningun archivo. Usa instalacion:continuar para seguir con la instalacion existente.\n"; return 1; }
        }
        echo "\nCUIT representado\nEs el CUIT de la persona o empresa por la que se emitiran comprobantes.\nARCA debe autorizar exactamente este CUIT para el alias elegido.\n\n";
        $cuit = (string) ($options['cuit'] ?? ($interactive ? $this->ask('CUIT representado') : ($previous['cuit'] ?? '')));
        echo "\nPunto de venta Web Services\nDebe existir en el ambiente elegido y ser especifico para Web Services.\nNo es el mismo que un punto usado por Comprobantes en Linea.\n\n";
        $point = (string) ($options['point-of-sale'] ?? ($interactive ? $this->ask('Punto de venta Web Services', (string) ($previous['point_of_sale'] ?? '1')) : ($previous['point_of_sale'] ?? '')));
        echo "\n" . $guide->aliasExplanation() . "\n\n";
        $alias = (string) ($options['alias'] ?? ($interactive ? $this->ask('Alias descriptivo', (string) ($previous['alias'] ?? 'arca')) : ($previous['alias'] ?? 'arca')));
        echo "\nResumen a guardar\nAmbiente: {$environment}\nCUIT terminado en: " . substr(preg_replace('/\D/', '', $cuit) ?? '', -4) . "\nPunto de venta: {$point}\nAlias: {$alias}\n\n";
        if ($interactive && strtolower($this->ask('Guardar esta configuracion y generar clave + CSR? [S/n]', 's')) === 'n') { echo "No se modifico ningun archivo.\n"; return 1; }
        $result = $installer->configure(['environment' => $environment, 'cuit' => $cuit, 'point_of_sale' => $point, 'alias' => $alias], !isset($options['without-csr']), $reconfigure ? Installer::RECONFIGURE_CONFIRMATION : null);
        echo "[OK] Configuracion activa: {$this->root}\\.env\n";
        echo "[OK] Perfil conservado: {$result['profile']}\n";
        echo "[OK] SQLite separada: {$result['database']}\n";
        echo "[OK] Clave privada generada: {$result['key']}\n";
        echo "[OK] CSR para subir a ARCA: {$result['csr']}\n\n";
        $current = $stateStore->environment($environment) ?? [];
        $this->showProgress($stateStore, $environment);
        echo "\n" . $guide->certificateInstructions($current) . "\n";
        return 0;
    }
    /** @param array<string,string|bool> $options */
    private function installationStatus(array $options): int
    {
        $store = (new Installer($this->root))->state();
        $environment = isset($options['environment']) ? $this->normalizeEnvironment((string) $options['environment']) : $store->activeEnvironment();
        $state = $store->environment($environment);
        if ($state === null) { echo "Todavia no hay una instalacion preparada. Comenza con: php bin/arca install\n"; return 1; }
        $this->banner('Estado de instalacion');
        $this->resumeSummary($state, $store);
        return $this->showProgress($store, (string) $state['environment']);
    }
    /** @param array<string,string|bool> $options */
    private function continueInstallation(array $options): int
    {
        $installer = new Installer($this->root);
        $store = $installer->state();
        $environment = isset($options['environment']) ? $this->normalizeEnvironment((string) $options['environment']) : $store->activeEnvironment();
        $state = $store->environment($environment);
        if ($state === null) { return $this->install($options); }
        if ($store->activeEnvironment() !== $environment) { $installer->activateEnvironment((string) $environment); }
        $this->banner('Continuar instalacion');
        $this->resumeSummary($state, $store);
        return $this->showCurrentStep($state, $store);
    }
    /** @param array<string,string|bool> $options */
    private function prepareProduction(array $options): int
    {
        $installer = new Installer($this->root);
        $store = $installer->state();
        $existing = $store->environment('produccion');
        $this->banner('Preparar produccion');
        echo (new InstallationGuide())->productionInstructions() . "\n\n";
        if ($existing !== null && !isset($options['reconfigure'])) {
            $installer->activateEnvironment('produccion');
            $this->resumeSummary($existing, $store);
            return $this->showCurrentStep($existing, $store);
        }
        $homologation = $store->environment('homologacion');
        $interactive = $this->interactive();
        $cuit = (string) ($options['cuit'] ?? ($homologation['cuit'] ?? ($interactive ? $this->ask('CUIT de produccion') : '')));
        $point = (string) ($options['point-of-sale'] ?? ($interactive ? $this->ask('Punto de venta Web Services real de produccion') : ''));
        $alias = (string) ($options['alias'] ?? ($interactive ? $this->ask('Alias descriptivo de produccion', 'arca-produccion') : 'arca-produccion'));
        if ($existing !== null) {
            $confirmed = isset($options['yes']) || ($interactive && $this->ask('Escribi RECONFIGURAR para reemplazar produccion', '') === 'RECONFIGURAR');
            if (!$confirmed) { echo "Produccion no fue modificada.\n"; return 1; }
        }
        $result = $installer->configure(['environment' => 'produccion', 'cuit' => $cuit, 'point_of_sale' => $point, 'alias' => $alias], true, $existing !== null ? Installer::RECONFIGURE_CONFIRMATION : null, false);
        echo "[OK] Homologacion y su .env activo permanecen intactos.\n[OK] Perfil de produccion: {$result['profile']}\n[OK] Clave de produccion: {$result['key']}\n[OK] CSR de produccion: {$result['csr']}\n\nActiva produccion solo cuando corresponda: php bin/arca ambiente:cambiar produccion\n\n";
        $state = $store->environment('produccion') ?? [];
        $this->showProgress($store, 'produccion');
        echo "\n" . (new InstallationGuide())->certificateInstructions($state) . "\n";
        return 0;
    }
    /** @param array<string,string|bool> $options */
    private function changeEnvironment(array $options): int
    {
        if (!isset($options['_0'])) { throw new \ArcaFacturacion\Exception\ConfigurationException('Indica homologacion o produccion: ambiente:cambiar AMBIENTE'); }
        $environment = $this->normalizeEnvironment((string) $options['_0']);
        (new Installer($this->root))->activateEnvironment($environment);
        echo "[OK] Ambiente activo: {$environment}. Se cargo su perfil sin modificar el otro ambiente.\n";
        echo "Continua con: php bin/arca instalacion:continuar\n";
        return 0;
    }
    private function doctor(bool $online, bool $json): int
    {
        $report = (new Doctor($this->root))->run($online);
        if ($online && $report->ready()) { (new Installer($this->root))->markOnlineValidated(); }
        if ($json) { echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL; return $report->exitCode(); }
        $this->banner('doctor');
        $section = '';
        foreach ($report->checks as $check) { if ($check->section !== $section) { $section = $check->section; echo "\n{$section}\n"; } $this->line($check->status, $check->label . ': ' . $check->message); }
        if ($online && $report->has('Autenticacion WSAA', 'error')) {
            echo "\nResultado\nLa configuracion local es valida, pero ARCA todavia no autentico esta instalacion.\n\n";
            echo "Revisa que:\n- el certificado haya sido emitido por ARCA para este ambiente;\n- el alias este autorizado para WSFE;\n- el CUIT representado sea correcto;\n- no se este usando un certificado de homologacion en produccion o viceversa.\n";
        } else {
            echo "\nResultado\n" . ($report->ready() ? 'La instalacion esta lista para continuar.' : 'Hay pasos pendientes. El mensaje de cada control indica como resolverlos.') . PHP_EOL;
        }
        if ($online && $report->ready()) { echo "[OK] Autorizacion WSFE y conexion registradas en el progreso.\nSiguiente paso: php bin/arca factura:simular --total=15000\n"; }
        return $report->exitCode();
    }
    /** @param array<string,string|bool> $options */
    private function generateCertificate(array $options): int
    {
        $config = (new ConfigLoader($this->root))->load();
        $store = (new Installer($this->root))->state();
        $state = $store->environment($config->environment) ?? [];
        $force = isset($options['force']);
        if ($force && !isset($options['yes'])) {
            if (!$this->interactive() || $this->ask('Regenerar invalida el certificado anterior. Escribi REGENERAR', '') !== 'REGENERAR') {
                echo "No se reemplazo ninguna clave. Para retomar usa instalacion:continuar.\n";
                return 1;
            }
        }
        $alias = (string) ($options['alias'] ?? ($state['alias'] ?? 'arca'));
        $result = (new CsrGenerator())->generate(dirname($config->privateKeyPath), (string) ($options['cuit'] ?? $config->cuit), $alias, $force ? CsrGenerator::REPLACE_CONFIRMATION : null);
        $store->saveEnvironment($config->environment, ['alias' => $alias, 'key_path' => $result['key'], 'csr_path' => $result['csr'], 'certificate_obtained' => false, 'certificate_installed' => false, 'service_authorized' => false, 'connection_validated' => false], false);
        echo "[OK] Clave privada: {$result['key']}\n[OK] CSR para ARCA: {$result['csr']}\n\n";
        echo (new InstallationGuide())->certificateInstructions($store->environment($config->environment) ?? []) . "\n";
        return 0;
    }
    /** @param array<string,string|bool> $options */
    private function installCertificate(array $options): int
    {
        $path = (string) ($options['_0'] ?? '');
        if ($path === '') { throw new CertificateException('Indica la ruta completa: certificado:instalar RUTA'); }
        $installer = new Installer($this->root);
        $replacement = null;
        $config = (new ConfigLoader($this->root))->load();
        if (is_file($config->certificatePath)) {
            $confirmed = isset($options['replace']) && (isset($options['yes']) || ($this->interactive() && $this->ask('Ya existe un certificado. Escribi REEMPLAZAR_CERTIFICADO para sustituirlo', '') === 'REEMPLAZAR_CERTIFICADO'));
            if (!$confirmed) { throw new CertificateException('Ya existe un certificado instalado. No fue modificado. Para reemplazarlo usa --replace y confirma de forma explicita.'); }
            $replacement = \ArcaFacturacion\Certificate\CertificateInstaller::REPLACE_CONFIRMATION;
        }
        $info = $installer->installCertificate($path, $replacement);
        echo "[OK] Certificado valido y correspondiente a la clave privada.\n[OK] Vigente desde: {$info->validFrom->format('d/m/Y')}\n[OK] Valido hasta: {$info->validUntil->format('d/m/Y')}\n[OK] Restan {$info->daysRemaining} dias.\n\n";
        $state = $installer->state()->environment() ?? [];
        echo (new InstallationGuide())->authorizationInstructions($state) . "\n";
        return $info->expiringSoon ? 1 : 0;
    }
    /** @param array<string,string|bool> $options */
    private function simulate(array $options): int
    {
        $total = (float) ($options['total'] ?? 0);
        $arca = Arca::desdeEntorno($this->root);
        $summary = $arca->simular($arca->facturaC()->consumidorFinal()->conceptoProductos()->importeTotal($total), isset($options['advanced']));
        (new Installer($this->root))->markSimulation();
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        echo "\n[OK] Primera simulacion registrada. No se envio ninguna solicitud a ARCA.\nSiguiente paso: php bin/arca instalacion:continuar\n";
        return 0;
    }
    /** @param array<string,string|bool> $options */
    private function testInvoice(array $options): int
    {
        $total = (float) ($options['total'] ?? 0);
        $key = (string) ($options['idempotency'] ?? 'prueba-' . date('Ymd-His'));
        $arca = Arca::desdeEntorno($this->root);
        $invoice = $arca->facturaC()->consumidorFinal()->conceptoProductos()->importeTotal($total);
        echo json_encode($arca->simular($invoice), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $config = (new ConfigLoader($this->root))->load();
        $warning = $config->isProduction() ? 'Esto emitira un comprobante fiscal REAL.' : 'Esto emitira un comprobante de prueba en homologacion.';
        $confirmed = isset($options['yes']) || ($this->interactive() && $this->ask($warning . ' Escribi EMITIR para continuar', 'NO') === 'EMITIR');
        if (!$confirmed) { echo "No se envio ninguna solicitud a ARCA.\n"; return 0; }
        $result = $arca->emitir($invoice, $key);
        if ($result->fueAutorizado()) { (new Installer($this->root))->markFirstEmission(); }
        echo $result->toJson() . PHP_EOL;
        return $result->fueAutorizado() ? 0 : ($result->esIncierto() ? 3 : 2);
    }
    /** @param array<string,string|bool> $options */
    private function listOperations(array $options): int
    {
        $items = Arca::desdeEntorno($this->root)->operacionesRecientes((int) ($options['limit'] ?? 20));
        foreach ($items as $item) { echo implode(' | ', [$item->operationId, $item->status, 'tipo=' . $item->voucherType, 'pv=' . $item->salesPoint, 'nro=' . ($item->voucherNumber ?? '-'), 'total=' . number_format($item->amount, 2, '.', '')]) . PHP_EOL; }
        return 0;
    }
    /** @param array<string,string|bool> $options */
    private function recover(array $options): int
    {
        $id = (string) ($options['_0'] ?? '');
        if ($id === '') { throw new \ArcaFacturacion\Exception\ValidationException(['Indica el operation_id que deseas recuperar.']); }
        echo Arca::desdeEntorno($this->root)->recuperar($id)->toJson() . PHP_EOL;
        return 0;
    }
    /** @param array<string,mixed> $state */
    private function resumeSummary(array $state, InstallationStateStore $store): void
    {
        $this->recoverySummary($state);
        echo 'Ambiente: ' . $state['environment'] . "\nCUIT terminado en: " . substr((string) $state['cuit'], -4) . "\nPunto de venta: " . $state['point_of_sale'] . "\nAlias configurado: " . $state['alias'] . "\n\n";
        echo 'Estas en el paso ' . $store->currentStep((string) $state['environment']) . " de 6.\n";
    }
    /** @param array<string,mixed> $state */
    private function recoverySummary(array $state): void
    {
        if (!(bool) ($state['recovered_from_legacy'] ?? false)) { echo 'Encontramos una instalacion incompleta o preparada.' . PHP_EOL . PHP_EOL; return; }
        echo 'Encontramos una instalacion creada por una version anterior del asistente.' . PHP_EOL;
        echo 'Reconstruiremos su estado sin modificar claves ni certificados.' . PHP_EOL . PHP_EOL;
        echo 'Archivos encontrados:' . PHP_EOL;
        foreach (($state['recovered_files'] ?? []) as $file) { echo '- ' . $file . PHP_EOL; }
        echo PHP_EOL;
    }
    private function showProgress(InstallationStateStore $store, string $environment): int
    {
        echo "\nEstado de instalacion ({$environment}):\n\n";
        foreach ($store->checklist($environment) as $check) { echo '[' . ($check['status'] === 'ok' ? 'OK' : 'PENDIENTE') . '] ' . $check['label'] . "\n"; }
        echo "\nPaso actual: " . $store->currentStep($environment) . " de 6.\n";
        return 0;
    }
    /** @param array<string,mixed> $state */
    private function showCurrentStep(array $state, InstallationStateStore $store): int
    {
        $environment = (string) $state['environment'];
        $this->showProgress($store, $environment);
        $guide = new InstallationGuide();
        $step = $store->currentStep($environment);
        echo "\n" . match ($step) {
            1, 2, 3 => $guide->certificateInstructions($state),
            4 => $guide->authorizationInstructions($state),
            5 => $guide->validationInstructions($state),
            default => $guide->firstEmissionInstructions($state),
        } . "\n";
        return 0;
    }
    private function showEnvironmentChecks(): void
    {
        $doctor = (new Doctor($this->root))->run(false);
        echo "PASO 1 DE 6 - Comprobar el entorno\n\nEl sistema verifica PHP y las extensiones necesarias antes de crear archivos.\n";
        foreach ($doctor->checks as $check) { if ($check->section === 'Entorno' && (str_starts_with($check->label, 'Extension') || $check->label === 'PHP' || $check->label === 'Composer')) { $this->line($check->status, $check->label . ': ' . $check->message); } }
    }
    private function normalizeEnvironment(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '1' || $value === '') { return 'homologacion'; }
        if ($value === '2') { return 'produccion'; }
        if (!in_array($value, ['homologacion', 'produccion'], true)) { throw new \ArcaFacturacion\Exception\ConfigurationException('Elegi homologacion o produccion. Si es tu primera integracion, usa homologacion.'); }
        return $value;
    }
    private function unknown(string $command): int { fwrite(STDERR, "Comando desconocido: {$command}. Usa help.\n"); return 2; }
    private function banner(string $title): void { echo "==========================================\n arca_facturacion {$title}\n==========================================\n"; }
    private function line(string $status, string $message): void { echo '[' . (new \ArcaFacturacion\Diagnostics\Check('', '', $status))->displayStatus() . '] ' . $message . PHP_EOL; }
    private function ask(string $label, string $default = ''): string { echo $label . ($default !== '' && !str_contains($label, '[') ? " [{$default}]" : '') . ': '; $value = trim((string) fgets(STDIN)); return $value !== '' ? $value : $default; }
    private function interactive(): bool { return function_exists('stream_isatty') ? stream_isatty(STDIN) : true; }
    /**
     * @param list<string> $arguments
     * @return array<string,string|bool>
     */
    private function options(array $arguments): array
    {
        $result = []; $position = 0;
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--')) {
                $value = substr($argument, 2);
                if (str_contains($value, '=')) { [$key, $item] = explode('=', $value, 2); $result[$key] = $item; }
                else { $result[$value] = true; }
            } else { $result['_' . $position++] = $argument; }
        }
        return $result;
    }
}
