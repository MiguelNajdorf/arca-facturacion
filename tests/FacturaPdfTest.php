<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Invoice\InvoiceBuilder;
use ArcaFacturacion\Invoice\InvoiceResult;
use ArcaFacturacion\Pdf\FacturaPdf;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
final class FacturaPdfTest extends TestCase
{
    private const EMISOR = ['razon_social' => 'Panadería Ñandú S.R.L.', 'domicilio' => 'Av. Siempreviva 742, Córdoba', 'condicion_iva' => 'IVA Responsable Inscripto', 'ingresos_brutos' => '901-123456-7', 'inicio_actividades' => '2020-03-01'];

    public function testGeneratesPdfForInvoiceAWithItems(): void
    {
        $invoice = (new InvoiceBuilder(1, 'Factura A'))->clienteCuit('20123456786')->netoGravado(15000)->iva(21, 10000)->iva(10.5, 5000)->importeTotal(17625)->construir();
        $pdf = (new FacturaPdf('30712345671'))->generar($invoice, $this->authorized(1), [
            'emisor' => self::EMISOR,
            'receptor' => ['nombre' => 'Cliente S.A.', 'domicilio' => 'Calle 1 123'],
            'items' => [['descripcion' => 'Servicio técnico', 'cantidad' => 2, 'precio' => 5000], ['descripcion' => 'Repuesto', 'precio' => 5000]],
            'observaciones' => 'Gracias por su compra.',
        ]);
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
    }
    public function testItemsMustMatchAuthorizedAmounts(): void
    {
        // En clase A los items van sin IVA: 12100 es el total con IVA, no el neto.
        $invoice = (new InvoiceBuilder(1, 'Factura A'))->clienteCuit('20123456786')->netoGravado(10000)->iva(21, 10000)->importeTotal(12100)->construir();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/sin IVA/');
        (new FacturaPdf('30712345671'))->generar($invoice, $this->authorized(1), ['emisor' => self::EMISOR, 'items' => [['descripcion' => 'Producto', 'precio' => 12100]]]);
    }
    public function testRequiresIssuerName(): void
    {
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->importeTotal(100)->construir();
        $this->expectException(ValidationException::class);
        (new FacturaPdf('30712345671'))->generar($invoice, $this->authorized(11), ['emisor' => []]);
    }
    public function testRejectsUnauthorizedResult(): void
    {
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->importeTotal(100)->construir();
        $this->expectException(ValidationException::class);
        (new FacturaPdf('30712345671'))->generar($invoice, new InvoiceResult('unknown', 'op', 'venta-1'), ['emisor' => self::EMISOR]);
    }
    private function authorized(int $type): InvoiceResult
    {
        return new InvoiceResult('authorized', 'op', 'venta-1', '70417054367476', new DateTimeImmutable('2026-11-30'), 94, 3, $type, new DateTimeImmutable('2026-11-20'));
    }
}
