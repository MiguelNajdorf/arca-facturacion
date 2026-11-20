<?php
declare(strict_types=1);

namespace ArcaFacturacion\Tests;

use ArcaFacturacion\Certificate\OpenSslConfiguration;
use PHPUnit\Framework\TestCase;

final class OpenSslConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/arca-openssl-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/php/extras/ssl', 0700, true);
        mkdir($this->root . '/apache/bin', 0700, true);
        file_put_contents($this->root . '/php/php.ini', '[PHP]');
        file_put_contents($this->root . '/php/extras/ssl/openssl.cnf', 'openssl_conf = openssl_init');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        @rmdir($this->root);
    }

    public function testFindsConfigurationFromLoadedPhpIniWhenBinaryBelongsToApache(): void
    {
        $located = (new OpenSslConfiguration())->locate(
            $this->root . '/php/php.ini',
            $this->root . '/apache/bin/httpd.exe',
            $this->root . '/php/ext',
            false
        );

        self::assertSame(
            realpath($this->root . '/php/extras/ssl/openssl.cnf'),
            $located
        );
    }

    public function testCurrentPhpCanGenerateKeyWithDiscoveredConfiguration(): void
    {
        $options = (new OpenSslConfiguration())->options([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);

        self::assertNotFalse(openssl_pkey_new($options));
    }
}
