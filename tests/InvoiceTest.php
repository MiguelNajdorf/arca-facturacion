<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Invoice\InvoiceBuilder;
use ArcaFacturacion\Invoice\InvoiceValidator;
use ArcaFacturacion\Infrastructure\RequestMapper;
use PHPUnit\Framework\TestCase;
final class InvoiceTest extends TestCase
{
    public function testBuildsAndMapsSimpleInvoiceC(): void
    {
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->conceptoProductos()->importeTotal(15000)->construir();
        (new InvoiceValidator())->validate($invoice);
        $request = (new RequestMapper())->map($invoice, 3, 42);
        self::assertSame(3, $request['FeCAEReq']['FeCabReq']['PtoVta']);
        self::assertSame(42, $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['CbteDesde']);
        self::assertArrayNotHasKey('Iva', $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']);
    }
    public function testRejectsTotalsThatDoNotClose(): void
    {
        $invoice = (new InvoiceBuilder(6, 'Factura B'))->clienteCuit('20123456786')->netoGravado(100)->iva21(20)->importeTotal(121)->construir();
        $this->expectException(ValidationException::class);
        (new InvoiceValidator())->validate($invoice);
    }
    public function testCreditNoteRequiresAssociation(): void
    {
        $invoice = (new InvoiceBuilder(13, 'Nota de credito C'))->consumidorFinal()->importeTotal(100)->construir();
        $this->expectException(ValidationException::class);
        (new InvoiceValidator())->validate($invoice);
    }
}
