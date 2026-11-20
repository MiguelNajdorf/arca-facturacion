<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Console\Application;
use PHPUnit\Framework\TestCase;
final class CliFlowTest extends TestCase
{
    public function testInitialCliFlowCreatesCsrSqliteAndSimulates(): void
    {
        $root = sys_get_temp_dir() . '/arca-cli-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);
        $application = new Application($root);
        ob_start();
        $installCode = $application->run(['arca', 'install', '--environment=homologacion', '--cuit=20123456786', '--point-of-sale=1', '--alias=prueba-cli']);
        $installOutput = (string) ob_get_clean();
        self::assertSame(0, $installCode);
        self::assertStringContainsString('CSR para subir a ARCA', $installOutput);
        self::assertFileExists($root . '/.env');
        self::assertFileExists($root . '/storage/arca-homologacion.sqlite');
        self::assertFileExists($root . '/storage/instalacion.json');
        self::assertFileExists($root . '/storage/configuracion/homologacion.env');
        self::assertFileExists($root . '/storage/certificados/homologacion/privada.key');
        self::assertFileExists($root . '/storage/certificados/homologacion/solicitud.csr');
        ob_start();
        $simulationCode = $application->run(['arca', 'factura:simular', '--total=15000']);
        $simulationOutput = (string) ob_get_clean();
        self::assertSame(0, $simulationCode);
        self::assertStringContainsString('No se envio ninguna solicitud', $simulationOutput);
        $this->removeDirectory($root);
    }
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) { return; }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($directory);
    }
}
