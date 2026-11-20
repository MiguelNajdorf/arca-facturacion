<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Support\Cuit;
use PHPUnit\Framework\TestCase;
final class CuitTest extends TestCase
{
    public function testValidatesAndNormalizesCuit(): void
    {
        self::assertSame('20123456786', Cuit::normalizar('20-12345678-6'));
        self::assertTrue(Cuit::esValido('20123456786'));
        self::assertFalse(Cuit::esValido('20123456780'));
    }
}
