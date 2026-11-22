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
    public function testMapsSeveralVatRatesWithCalculatedAmounts(): void
    {
        // 10000 al 21% + 5000 al 10,5%: IVA 2100 + 525 = 2625; total 17625
        $invoice = (new InvoiceBuilder(1, 'Factura A'))->clienteCuit('20123456786')->netoGravado(15000)
            ->iva(21, 10000)->iva(10.5, 5000)->importeTotal(17625)->construir();
        (new InvoiceValidator())->validate($invoice);
        self::assertSame(2625.0, $invoice->vat);
        $detail = (new RequestMapper())->map($invoice, 1, 1)['FeCAEReq']['FeDetReq']['FECAEDetRequest'];
        self::assertSame(2625.0, $detail['ImpIVA']);
        self::assertSame([
            ['Id' => 5, 'BaseImp' => 10000.0, 'Importe' => 2100.0],
            ['Id' => 4, 'BaseImp' => 5000.0, 'Importe' => 525.0],
        ], $detail['Iva']['AlicIva']);
    }
    public function testMapsEveryWsfeVatRate(): void
    {
        $builder = (new InvoiceBuilder(6, 'Factura B'))->clienteCuit('20123456786')->netoGravado(600);
        foreach ([0, 2.5, 5, 10.5, 21, 27] as $rate) { $builder = $builder->iva($rate, 100); }
        $invoice = $builder->importeTotal(600 + 66)->construir();
        (new InvoiceValidator())->validate($invoice);
        $ids = array_column((new RequestMapper())->map($invoice, 1, 1)['FeCAEReq']['FeDetReq']['FECAEDetRequest']['Iva']['AlicIva'], 'Id');
        self::assertSame([3, 9, 8, 4, 5, 6], $ids);
    }
    public function testIva21KeepsWorkingAsShortcut(): void
    {
        $invoice = (new InvoiceBuilder(6, 'Factura B'))->clienteCuit('20123456786')->netoGravado(10000)->iva21(2100)->importeTotal(12100)->construir();
        (new InvoiceValidator())->validate($invoice);
        self::assertSame([['rate' => 21.0, 'base' => 10000.0, 'amount' => 2100.0]], $invoice->vatBreakdown);
    }
    public function testRejectsVatBasesThatDoNotMatchNet(): void
    {
        $invoice = (new InvoiceBuilder(1, 'Factura A'))->clienteCuit('20123456786')->netoGravado(15000)->iva(21, 10000)->importeTotal(17100)->construir();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/bases de IVA/');
        (new InvoiceValidator())->validate($invoice);
    }
    public function testRejectsUnsupportedVatRate(): void
    {
        $invoice = (new InvoiceBuilder(1, 'Factura A'))->clienteCuit('20123456786')->netoGravado(100)->iva(19, 100)->importeTotal(119)->construir();
        $this->expectException(ValidationException::class);
        (new RequestMapper())->map($invoice, 1, 1);
    }
    public function testCreditNoteRequiresAssociation(): void
    {
        $invoice = (new InvoiceBuilder(13, 'Nota de credito C'))->consumidorFinal()->importeTotal(100)->construir();
        $this->expectException(ValidationException::class);
        (new InvoiceValidator())->validate($invoice);
    }
}
