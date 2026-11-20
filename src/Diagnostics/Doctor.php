<?php
declare(strict_types=1);
namespace ArcaFacturacion\Diagnostics;
use ArcaFacturacion\Certificate\CertificateValidator;
use ArcaFacturacion\Config\ConfigLoader;
use ArcaFacturacion\Receipt\SQLiteReceiptRepository;
use Throwable;
final class Doctor
{
    public function __construct(private readonly string $root, private readonly ?OnlineDiagnostics $onlineDiagnostics = null) {}
    public function run(bool $online = false): DoctorReport
    {
        $checks = [];
        $checks[] = new Check('Entorno', 'PHP', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'error', PHP_VERSION);
        foreach (['soap', 'openssl', 'json', 'SimpleXML', 'pdo_sqlite', 'sqlite3'] as $extension) { $checks[] = new Check('Entorno', 'Extension ' . $extension, extension_loaded($extension) ? 'ok' : 'error', extension_loaded($extension) ? 'habilitada' : $this->help($extension)); }
        $checks[] = new Check('Entorno', 'Composer', is_file($this->root . '/vendor/autoload.php') ? 'ok' : 'warning', is_file($this->root . '/vendor/autoload.php') ? 'dependencias instaladas' : 'Ejecuta composer install.');
        try {
            $config = (new ConfigLoader($this->root))->load();
            $checks[] = new Check('Configuracion', 'Archivo y variables', 'ok', 'configuracion valida');
            $checks[] = new Check('Configuracion', 'Ambiente', 'ok', $config->environment);
            $checks[] = new Check('Configuracion', 'CUIT y punto de venta', 'ok', 'CUIT terminado en ' . substr($config->cuit, -4) . ', punto ' . $config->salesPoint);
            $storageWritable = (is_dir($config->storagePath) || @mkdir($config->storagePath, 0700, true)) && is_writable($config->storagePath);
            $checks[] = new Check('Entorno', 'Almacenamiento', $storageWritable ? 'ok' : 'error', $storageWritable ? 'escribible y fuera de public/' : 'No se puede escribir en storage.');
            try { new SQLiteReceiptRepository($config->databasePath()); $checks[] = new Check('Persistencia', 'SQLite', 'ok', 'base preparada con WAL'); }
            catch (Throwable $error) { $checks[] = new Check('Persistencia', 'SQLite', 'error', $error->getMessage()); }
            try {
                $info = (new CertificateValidator())->validate($config->certificatePath, $config->privateKeyPath, $config->keyPassphrase);
                $checks[] = new Check('Certificados', 'Validacion local X.509', 'ok', 'el certificado es X.509 valido');
                $checks[] = new Check('Certificados', 'Correspondencia con clave privada', 'ok', 'el certificado corresponde a la clave privada');
                $checks[] = new Check('Certificados', 'Vigencia local', $info->expiringSoon ? 'warning' : 'ok', 'hasta ' . $info->validUntil->format('d/m/Y') . ' (' . $info->daysRemaining . ' dias)');
                if ($online) {
                    $checks = array_merge($checks, ($this->onlineDiagnostics ?? new OnlineDiagnostics())->run($config));
                } else { $checks[] = new Check('Conectividad', 'Servicios ARCA', 'info', 'No comprobados. Usa doctor --online.'); }
            } catch (Throwable $error) { $checks[] = new Check('Certificados', 'Validacion local', 'error', $error->getMessage()); }
        } catch (Throwable $error) { $checks[] = new Check('Configuracion', 'Archivo y variables', 'error', $error->getMessage()); }
        return new DoctorReport($checks);
    }
    private function help(string $extension): string
    {
        if (PHP_OS_FAMILY === 'Windows') { return "Habilita extension={$extension} en php.ini y reinicia Apache o la terminal."; }
        return "Instala o habilita la extension PHP {$extension} y vuelve a ejecutar doctor.";
    }
}
