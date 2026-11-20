<?php
declare(strict_types=1);

namespace ArcaFacturacion\Tests;

use ArcaFacturacion\Config\ArcaConfig;
use ArcaFacturacion\Diagnostics\Check;
use ArcaFacturacion\Diagnostics\Doctor;
use ArcaFacturacion\Diagnostics\OnlineDiagnostics;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DoctorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/arca-doctor-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/storage/certificados/homologacion', 0700, true);
        $this->createSelfSignedCertificate();
        file_put_contents($this->root . '/.env', implode(PHP_EOL, [
            'ARCA_ENVIRONMENT=homologacion',
            'ARCA_CUIT=20123456786',
            'ARCA_POINT_OF_SALE=1',
            'ARCA_CERT_PATH=storage/certificados/homologacion/certificado.crt',
            'ARCA_KEY_PATH=storage/certificados/homologacion/privada.key',
            'ARCA_STORAGE_PATH=storage',
        ]) . PHP_EOL);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($this->root);
    }

    public function testWsaaRejectionNeverCoexistsWithGeneralWsaaWsfeSuccess(): void
    {
        $report = (new Doctor($this->root, new RejectingWsaaDiagnostics()))->run(true);

        self::assertCheck($report->checks, 'Validacion local X.509', 'ok');
        self::assertCheck($report->checks, 'Correspondencia con clave privada', 'ok');
        self::assertCheck($report->checks, 'Endpoint WSAA', 'ok');
        self::assertCheck($report->checks, 'Endpoint WSFE', 'ok');
        self::assertCheck($report->checks, 'Firma local del Login Ticket Request', 'ok');
        self::assertCheck($report->checks, 'Autenticacion WSAA', 'error');
        self::assertCheck($report->checks, 'Ticket de Acceso', 'not_checked');
        self::assertCheck($report->checks, 'Autenticacion WSFE', 'not_checked');
        self::assertCheck($report->checks, 'CUIT representado', 'not_checked');
        self::assertCheck($report->checks, 'Punto de venta', 'not_checked');
        self::assertFalse($report->ready());
        self::assertSame(2, $report->exitCode());

        foreach ($report->checks as $check) {
            self::assertNotSame('WSAA y WSFE', $check->label);
            self::assertFalse($check->status === 'ok' && str_contains($check->label, 'WSAA') && str_contains($check->label, 'WSFE'));
        }
        self::assertSame('NO COMPROBADO', (new Check('', '', 'not_checked'))->displayStatus());
    }

    /** @param list<Check> $checks */
    private static function assertCheck(array $checks, string $label, string $status): void
    {
        foreach ($checks as $check) {
            if ($check->label === $label) { self::assertSame($status, $check->status, $label); return; }
        }
        self::fail('No se encontro el control: ' . $label);
    }

    private function createSelfSignedCertificate(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $bundled = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_readable($bundled)) { $options['config'] = $bundled; }
        $key = openssl_pkey_new($options);
        self::assertNotFalse($key);
        $csr = openssl_csr_new(['commonName' => 'diagnostico-autofirmado'], $key, $options);
        self::assertNotFalse($csr);
        $certificate = openssl_csr_sign($csr, null, $key, 365, $options);
        self::assertNotFalse($certificate);
        self::assertTrue(openssl_pkey_export($key, $keyPem, null, $options));
        self::assertTrue(openssl_x509_export($certificate, $certificatePem));
        file_put_contents($this->root . '/storage/certificados/homologacion/privada.key', $keyPem);
        file_put_contents($this->root . '/storage/certificados/homologacion/certificado.crt', $certificatePem);
    }
}

final class RejectingWsaaDiagnostics extends OnlineDiagnostics
{
    protected function resolveDns(string $url): string { return (string) parse_url($url, PHP_URL_HOST); }
    protected function connectHttps(string $url, int $timeout): void {}
    protected function createSoapClient(string $url, ArcaConfig $config, bool $wsfe): object { return (object) ['wsfe' => $wsfe]; }
    protected function probeEndpoint(object $client, string $service, bool $probeHealth): void {}
    protected function signLoginTicketRequest(ArcaConfig $config): string { return 'cms-local-valido'; }
    protected function authenticateWsaa(object $client, string $cms): string { throw new RuntimeException('certificado rechazado'); }
}
