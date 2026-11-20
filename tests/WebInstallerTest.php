<?php
declare(strict_types=1);

namespace ArcaFacturacion\Tests;

use ArcaFacturacion\Console\Application;
use ArcaFacturacion\Installation\Installer;
use ArcaFacturacion\Installation\LocalWebInstallerAccess;
use PHPUnit\Framework\TestCase;

final class WebInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/arca-web-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($this->root);
    }

    /** @dataProvider localRequests */
    public function testAcceptsCommonLocalDevelopmentHosts(string $remote, string $host): void
    {
        self::assertTrue((new LocalWebInstallerAccess())->isAllowed(['REMOTE_ADDR' => $remote, 'HTTP_HOST' => $host]));
    }

    /** @return iterable<string,array{string,string}> */
    public static function localRequests(): iterable
    {
        yield 'Laragon local' => ['127.0.0.1', 'arca_facturacion.local'];
        yield 'test domain' => ['127.0.0.1', 'arca_facturacion.test'];
        yield 'localhost subpath' => ['::1', 'localhost'];
        yield 'IPv4 with port' => ['127.0.0.1', '127.0.0.1:8080'];
        yield 'IPv6 loopback' => ['::1', '[::1]:8080'];
    }

    public function testRejectsRemoteRequestsEvenWhenHostLooksLocal(): void
    {
        $guard = new LocalWebInstallerAccess();
        self::assertFalse($guard->isAllowed(['REMOTE_ADDR' => '192.168.1.50', 'HTTP_HOST' => 'arca_facturacion.local']));
        self::assertFalse($guard->isAllowed(['REMOTE_ADDR' => '203.0.113.8', 'HTTP_HOST' => 'localhost']));
        self::assertFalse($guard->isAllowed(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com']));
    }

    public function testCliResumesConfigurationCreatedThroughSharedWebCore(): void
    {
        (new Installer($this->root))->configure(['environment' => 'homologacion', 'cuit' => '20123456786', 'point_of_sale' => 1, 'alias' => 'iniciado-desde-web']);
        ob_start();
        $code = (new Application($this->root))->run(['arca', 'instalacion:continuar']);
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Alias configurado: iniciado-desde-web', $output);
        self::assertStringContainsString('Estas en el paso 3 de 6', $output);
    }

    public function testWebEntryUsesSharedCoreWithoutLoginOrEnablementToken(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/public/install/index.php');

        self::assertStringContainsString('new Installer($root)', $source);
        self::assertStringContainsString('LocalWebInstallerAccess', $source);
        self::assertStringContainsString('Bienvenido a arca_facturacion', $source);
        self::assertStringContainsString('Retomar instalacion', $source);
        self::assertStringContainsString('Instalacion completada', $source);
        self::assertStringContainsString('$installPath = __DIR__', $source);
        self::assertStringNotContainsString('PHP_AUTH', $source);
        self::assertStringNotContainsString('WWW-Authenticate', $source);
        self::assertStringNotContainsString('ARCA_WEB_INSTALLER', $source);
        self::assertStringNotContainsString('unlink(__DIR__', $source);
        self::assertFileExists(dirname(__DIR__) . '/bin/arca');
    }
}
