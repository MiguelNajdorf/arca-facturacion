<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Certificate\CertificateValidator;
use PHPUnit\Framework\TestCase;
final class CertificateTest extends TestCase
{
    public function testValidatesMatchingCertificateAndKey(): void
    {
        $directory = sys_get_temp_dir() . '/arca-cert-' . bin2hex(random_bytes(6)); mkdir($directory, 0700);
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $bundled = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_readable($bundled)) { $options['config'] = $bundled; }
        $key = openssl_pkey_new($options);
        self::assertNotFalse($key);
        $csr = openssl_csr_new(['commonName' => 'arca-test'], $key, $options);
        $certificate = openssl_csr_sign($csr, null, $key, 30, $options);
        openssl_pkey_export($key, $keyPem, null, $options); openssl_x509_export($certificate, $certPem);
        file_put_contents($directory . '/key.pem', $keyPem); file_put_contents($directory . '/cert.pem', $certPem);
        $info = (new CertificateValidator())->validate($directory . '/cert.pem', $directory . '/key.pem');
        self::assertTrue($info->matchesPrivateKey);
        self::assertGreaterThan(0, $info->daysRemaining);
        @unlink($directory . '/key.pem'); @unlink($directory . '/cert.pem'); @rmdir($directory);
    }
}
