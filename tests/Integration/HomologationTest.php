<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests\Integration;
use ArcaFacturacion\Arca;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
#[Group('homologation')]
final class HomologationTest extends TestCase
{
    public function testServicesOnlyWhenExplicitlyEnabled(): void
    {
        if (getenv('ARCA_RUN_HOMOLOGATION') !== '1') { self::markTestSkipped('Define ARCA_RUN_HOMOLOGATION=1 para probar credenciales reales.'); }
        self::assertNotEmpty(Arca::desdeEntorno(dirname(__DIR__, 2))->estadoServicios());
    }
}
