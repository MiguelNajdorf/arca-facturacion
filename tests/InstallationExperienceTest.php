<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Config\ConfigLoader;
use ArcaFacturacion\Config\ConfigWriter;
use ArcaFacturacion\Console\Application;
use ArcaFacturacion\Certificate\CsrGenerator;
use ArcaFacturacion\Exception\CertificateException;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Installation\InstallationStateStore;
use ArcaFacturacion\Installation\InstallationGuide;
use ArcaFacturacion\Installation\Installer;
use PHPUnit\Framework\TestCase;
final class InstallationExperienceTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/arca-install-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }
    protected function tearDown(): void { $this->removeDirectory($this->root); }
    public function testCustomAliasIsPersistedAndShownInStatus(): void
    {
        $application = new Application($this->root);
        [$code] = $this->runCli($application, ['arca', 'install', '--environment=homologacion', '--cuit=20123456786', '--point-of-sale=1', '--alias=sistema-ventas-pruebas']);
        self::assertSame(0, $code);
        [$statusCode, $output] = $this->runCli($application, ['arca', 'instalacion:estado']);
        self::assertSame(0, $statusCode);
        self::assertStringContainsString('Alias configurado: sistema-ventas-pruebas', $output);
        self::assertStringContainsString('Estas en el paso 3 de 6', $output);
        $state = (new InstallationStateStore($this->root))->environment('homologacion');
        self::assertSame('sistema-ventas-pruebas', $state['alias'] ?? null);
    }
    public function testHomologationStepThreeShowsRealValuesAndCompleteWsassInstructions(): void
    {
        $key = 'C:/instalacion-real/segura/privada.key';
        $csr = 'C:/instalacion-real/segura/solicitud.csr';
        $alias = 'alias-unico-del-usuario';
        $text = (new InstallationGuide())->certificateInstructions(['environment' => 'homologacion', 'key_path' => $key, 'csr_path' => $csr, 'alias' => $alias]);

        self::assertStringContainsString($key, $text);
        self::assertStringContainsString($csr, $text);
        self::assertStringContainsString($alias, $text);
        self::assertStringContainsString('No la subas a ARCA', $text);
        self::assertStringContainsString('No la compartas', $text);
        self::assertStringContainsString('No la envies por correo', $text);
        self::assertStringContainsString('No la subas a GitHub', $text);
        self::assertStringContainsString('-----BEGIN CERTIFICATE REQUEST-----', $text);
        self::assertStringContainsString('-----END CERTIFICATE REQUEST-----', $text);
        self::assertStringContainsString('php bin/arca certificado:instalar "C:\\ruta\\al\\certificado.crt"', $text);
        self::assertStringContainsString('Manual oficial para crear el certificado en WSASS', $text);
        self::assertStringContainsString('PAUSA: este paso ocurre fuera de arca_facturacion', $text);
        foreach (['arca-test', 'arca-homologacion', 'sistema-ventas-pruebas', 'mi-empresa-wsfe', 'facturacion-produccion'] as $example) { self::assertStringNotContainsString($example, $text); }
    }
    public function testWebGuideUsesBrowserActionsInsteadOfCliCommands(): void
    {
        $guide = new InstallationGuide();
        $state = ['environment' => 'homologacion', 'key_path' => 'C:/seguro/privada.key', 'csr_path' => 'C:/seguro/solicitud.csr', 'alias' => 'alias-real', 'cuit' => '20123456786'];

        $certificate = $guide->certificateInstructions($state, InstallationGuide::WEB);
        self::assertStringContainsString('volve a esta pagina', $certificate);
        self::assertStringContainsString('formulario que aparece debajo', $certificate);
        self::assertStringContainsString('Seleccionar archivo', $certificate);
        self::assertStringNotContainsString('volve a esta consola', $certificate);
        self::assertStringNotContainsString('php bin/arca certificado:instalar', $certificate);

        $authorization = $guide->authorizationInstructions($state, InstallationGuide::WEB);
        self::assertStringContainsString('Comprobar con ARCA y continuar', $authorization);
        self::assertStringNotContainsString('php bin/arca doctor --online', $authorization);

        $validation = $guide->validationInstructions($state, InstallationGuide::WEB);
        self::assertStringContainsString('Simular y finalizar', $validation);
        self::assertStringNotContainsString('php bin/arca factura:simular', $validation);
    }
    public function testInterruptedInstallResumesWithoutReplacingPrivateKey(): void
    {
        $application = new Application($this->root);
        $arguments = ['arca', 'install', '--environment=homologacion', '--cuit=20123456786', '--point-of-sale=1', '--alias=alias-elegido'];
        $this->runCli($application, $arguments);
        $key = $this->root . '/storage/certificados/homologacion/privada.key';
        $before = hash_file('sha256', $key);
        [$code, $output] = $this->runCli($application, $arguments);
        self::assertSame(0, $code);
        self::assertStringContainsString('Encontramos una instalacion incompleta', $output);
        self::assertStringContainsString('Alias configurado: alias-elegido', $output);
        self::assertSame($before, hash_file('sha256', $key));
    }
    public function testExistingConfigurationCannotBeOverwrittenWithoutExplicitPermission(): void
    {
        $installer = new Installer($this->root);
        $input = ['environment' => 'homologacion', 'cuit' => '20123456786', 'point_of_sale' => 1, 'alias' => 'primero'];
        $installer->configure($input);
        $key = $this->root . '/storage/certificados/homologacion/privada.key';
        $before = hash_file('sha256', $key);
        try { $installer->configure(array_merge($input, ['alias' => 'segundo'])); self::fail('Debio rechazar la sobrescritura.'); }
        catch (ConfigurationException $error) { self::assertStringContainsString('confirmacion explicita', $error->getMessage()); }
        self::assertSame($before, hash_file('sha256', $key));
    }
    public function testForceFlagAloneDoesNotRegenerateKeysInNonInteractiveMode(): void
    {
        $application = new Application($this->root);
        $this->runCli($application, ['arca', 'install', '--environment=homologacion', '--cuit=20123456786', '--point-of-sale=1', '--alias=seguro']);
        $key = $this->root . '/storage/certificados/homologacion/privada.key';
        $before = hash_file('sha256', $key);
        [$code, $output] = $this->runCli($application, ['arca', 'certificado:generar', '--force']);
        self::assertSame(1, $code);
        self::assertStringContainsString('No se reemplazo ninguna clave', $output);
        self::assertSame($before, hash_file('sha256', $key));
    }
    public function testInstallingCertificateAdvancesToAuthorizationPending(): void
    {
        $application = new Application($this->root);
        $this->runCli($application, ['arca', 'install', '--environment=homologacion', '--cuit=20123456786', '--point-of-sale=1', '--alias=cert-prueba']);
        $certificate = $this->matchingCertificate('homologacion');
        [$code, $output] = $this->runCli($application, ['arca', 'certificado:instalar', $certificate]);
        self::assertSame(0, $code);
        self::assertStringContainsString('PASO 4 DE 6', $output);
        self::assertStringContainsString('Crear Autorizacion a Servicio', $output);
        $state = (new InstallationStateStore($this->root))->environment('homologacion');
        self::assertTrue((bool) ($state['certificate_installed'] ?? false));
        self::assertFalse((bool) ($state['service_authorized'] ?? true));
        $installed = $this->root . '/storage/certificados/homologacion/certificado.crt';
        $certificateBefore = file_get_contents($installed);
        [$replacementCode] = $this->runCli($application, ['arca', 'certificado:instalar', $certificate]);
        self::assertSame(2, $replacementCode);
        self::assertSame($certificateBefore, file_get_contents($installed));
    }
    public function testProductionPreparationKeepsBothEnvironmentsSeparated(): void
    {
        $application = new Application($this->root);
        $this->runCli($application, ['arca', 'install', '--environment=homologacion', '--cuit=20123456786', '--point-of-sale=1', '--alias=mi-homo']);
        $homKey = $this->root . '/storage/certificados/homologacion/privada.key';
        $homHash = hash_file('sha256', $homKey);
        [$code, $output] = $this->runCli($application, ['arca', 'produccion:preparar', '--point-of-sale=2', '--alias=mi-produccion']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Homologacion y su .env activo permanecen intactos', $output);
        $productionKey = $this->root . '/storage/certificados/produccion/privada.key';
        self::assertFileExists($productionKey);
        self::assertNotSame($homHash, hash_file('sha256', $productionKey));
        self::assertSame($homHash, hash_file('sha256', $homKey));
        self::assertFileExists($this->root . '/storage/arca-homologacion.sqlite');
        self::assertFileExists($this->root . '/storage/arca-produccion.sqlite');
        self::assertFileExists($this->root . '/storage/configuracion/homologacion.env');
        self::assertFileExists($this->root . '/storage/configuracion/produccion.env');
        self::assertSame('homologacion', (new ConfigLoader($this->root))->load()->environment);
        [$productionSwitchCode] = $this->runCli($application, ['arca', 'ambiente:cambiar', 'produccion']);
        self::assertSame(0, $productionSwitchCode);
        self::assertSame('produccion', (new ConfigLoader($this->root))->load()->environment);
        [$switchCode] = $this->runCli($application, ['arca', 'ambiente:cambiar', 'homologacion']);
        self::assertSame(0, $switchCode);
        self::assertSame('homologacion', (new ConfigLoader($this->root))->load()->environment);
    }
    public function testProgressMovesOnlyAfterVerifiedMilestones(): void
    {
        $installer = new Installer($this->root);
        $installer->configure(['environment' => 'homologacion', 'cuit' => '20123456786', 'point_of_sale' => 1, 'alias' => 'progreso']);
        $store = $installer->state();
        self::assertSame(3, $store->currentStep('homologacion'));
        $store->mark('homologacion', 'certificate_obtained');
        $store->saveEnvironment('homologacion', ['certificate_installed' => true], false);
        self::assertSame(3, $store->currentStep('homologacion'), 'Un archivo ausente no debe contarse como certificado instalado.');
        file_put_contents($this->root . '/storage/certificados/homologacion/certificado.crt', 'fixture');
        self::assertSame(4, $store->currentStep('homologacion'));
        $installer->markOnlineValidated();
        self::assertSame(5, $store->currentStep('homologacion'));
        $installer->markSimulation();
        self::assertSame(6, $store->currentStep('homologacion'));
    }
    public function testLegacyInstallationWithoutProgressIsDetectedAndMigratedWithoutChangingFiles(): void
    {
        $paths = $this->createLegacyInstallation();
        $before = $this->contents($paths);
        self::assertFileDoesNotExist($this->root . '/storage/instalacion.json');

        [$code, $output] = $this->runCli(new Application($this->root), ['arca', 'instalacion:estado']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Encontramos una instalacion creada por una version anterior del asistente', $output);
        self::assertStringContainsString('Archivos encontrados', $output);
        self::assertStringNotContainsString('Todavia no hay una instalacion preparada', $output);
        self::assertFileExists($this->root . '/storage/instalacion.json');
        self::assertSame($before, $this->contents($paths));
    }
    public function testContinueMigratesLegacyInstallationAndPreservesKeyAndCsrByteForByte(): void
    {
        $paths = $this->createLegacyInstallation();
        $keyBefore = file_get_contents($paths['key']);
        $csrBefore = file_get_contents($paths['csr']);

        [$code, $output] = $this->runCli(new Application($this->root), ['arca', 'instalacion:continuar']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Estas en el paso 3 de 6', $output);
        self::assertSame($keyBefore, file_get_contents($paths['key']));
        self::assertSame($csrBefore, file_get_contents($paths['csr']));
    }
    public function testAmbiguousLegacyProfilesStopWithoutModifyingArtifacts(): void
    {
        $paths = $this->createLegacyInstallation();
        $before = $this->contents($paths);
        mkdir($this->root . '/storage/configuracion', 0700, true);
        file_put_contents($this->root . '/storage/configuracion/homologacion.env', str_replace('ARCA_POINT_OF_SALE=1', 'ARCA_POINT_OF_SALE=2', (string) file_get_contents($paths['env'])));

        try {
            (new InstallationStateStore($this->root))->read();
            self::fail('Una configuracion heredada ambigua debe detenerse.');
        } catch (ConfigurationException $error) {
            self::assertStringContainsString('incompatibles', $error->getMessage());
        }
        self::assertFileDoesNotExist($this->root . '/storage/instalacion.json');
        self::assertSame($before, $this->contents($paths));
    }
    public function testWriterAndGeneratorRefusePhysicalOverwriteWithoutServiceConfirmation(): void
    {
        $directory = $this->root . '/storage/certificados/homologacion';
        mkdir($directory, 0700, true);
        file_put_contents($directory . '/privada.key', 'clave-anterior');
        file_put_contents($directory . '/solicitud.csr', 'csr-anterior');

        try {
            (new CsrGenerator())->generate($directory, '20123456786', 'seguro');
            self::fail('El generador no debe reemplazar archivos fisicos.');
        } catch (CertificateException $error) {
            self::assertStringContainsString('privada.key', $error->getMessage());
        }
        $writer = new ConfigWriter($this->root);
        file_put_contents($this->root . '/.env', 'anterior');
        try {
            $writer->write(['ARCA_ENVIRONMENT' => 'homologacion']);
            self::fail('El escritor no debe reemplazar .env.');
        } catch (ConfigurationException $error) {
            self::assertStringContainsString('.env', $error->getMessage());
        }
        $profile = $this->root . '/storage/configuracion/homologacion.env';
        mkdir(dirname($profile), 0700, true);
        file_put_contents($profile, 'perfil-anterior');
        try {
            $writer->writeProfile('homologacion', ['ARCA_ENVIRONMENT' => 'homologacion']);
            self::fail('El escritor no debe reemplazar perfiles.');
        } catch (ConfigurationException $error) {
            self::assertStringContainsString('homologacion.env', $error->getMessage());
        }
        self::assertSame('clave-anterior', file_get_contents($directory . '/privada.key'));
        self::assertSame('csr-anterior', file_get_contents($directory . '/solicitud.csr'));
        self::assertSame('anterior', file_get_contents($this->root . '/.env'));
        self::assertSame('perfil-anterior', file_get_contents($profile));
    }
    /** @param list<string> $arguments @return array{int,string} */
    private function runCli(Application $application, array $arguments): array
    {
        ob_start(); $code = $application->run($arguments); $output = (string) ob_get_clean();
        return [$code, $output];
    }
    private function matchingCertificate(string $environment): string
    {
        $directory = $this->root . '/storage/certificados/' . $environment;
        $keyPem = (string) file_get_contents($directory . '/privada.key');
        $csrPem = (string) file_get_contents($directory . '/solicitud.csr');
        $key = openssl_pkey_get_private($keyPem);
        self::assertNotFalse($key);
        $options = ['digest_alg' => 'sha256'];
        $bundled = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_readable($bundled)) { $options['config'] = $bundled; }
        $certificate = openssl_csr_sign($csrPem, null, $key, 365, $options);
        self::assertNotFalse($certificate);
        self::assertTrue(openssl_x509_export($certificate, $pem));
        $path = $this->root . '/certificado-descargado.crt';
        file_put_contents($path, $pem);
        return $path;
    }
    /** @return array{env:string,key:string,csr:string,database:string} */
    private function createLegacyInstallation(): array
    {
        $directory = $this->root . '/storage/certificados/homologacion';
        $generated = (new CsrGenerator())->generate($directory, '20123456786', 'heredado');
        $database = $this->root . '/storage/arca-homologacion.sqlite';
        new \ArcaFacturacion\Receipt\SQLiteReceiptRepository($database);
        $env = $this->root . '/.env';
        file_put_contents($env, implode(PHP_EOL, [
            'ARCA_ENVIRONMENT=homologacion',
            'ARCA_CUIT=20123456786',
            'ARCA_POINT_OF_SALE=1',
            'ARCA_CERT_ALIAS=heredado',
            'ARCA_CERT_PATH=storage/certificados/homologacion/certificado.crt',
            'ARCA_KEY_PATH=storage/certificados/homologacion/privada.key',
            'ARCA_STORAGE_PATH=storage',
        ]) . PHP_EOL);
        return ['env' => $env, 'key' => $generated['key'], 'csr' => $generated['csr'], 'database' => $database];
    }
    /** @param array<string,string> $paths @return array<string,string> */
    private function contents(array $paths): array
    {
        $result = [];
        foreach ($paths as $name => $path) { $result[$name] = (string) file_get_contents($path); }
        return $result;
    }
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($directory);
    }
}
