<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Invoice\InvoiceBuilder;
use ArcaFacturacion\Invoice\InvoiceResult;
use ArcaFacturacion\Pdf\QrArca;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
final class QrArcaTest extends TestCase
{
    public function testMatchesOfficialExample(): void
    {
        // Ejemplo de la especificacion oficial de ARCA: mismo JSON, mismo Base64.
        $url = QrArca::codificar(['ver' => 1, 'fecha' => '2020-10-13', 'cuit' => 30000000007, 'ptoVta' => 10, 'tipoCmp' => 1, 'nroCmp' => 94, 'importe' => 12100, 'moneda' => 'DOL', 'ctz' => 65, 'tipoDocRec' => 80, 'nroDocRec' => 20000000001, 'tipoCodAut' => 'E', 'codAut' => 70417054367476]);
        self::assertSame('https://www.arca.gob.ar/fe/qr/?p=eyJ2ZXIiOjEsImZlY2hhIjoiMjAyMC0xMC0xMyIsImN1aXQiOjMwMDAwMDAwMDA3LCJwdG9WdGEiOjEwLCJ0aXBvQ21wIjoxLCJucm9DbXAiOjk0LCJpbXBvcnRlIjoxMjEwMCwibW9uZWRhIjoiRE9MIiwiY3R6Ijo2NSwidGlwb0RvY1JlYyI6ODAsIm5yb0RvY1JlYyI6MjAwMDAwMDAwMDEsInRpcG9Db2RBdXQiOiJFIiwiY29kQXV0Ijo3MDQxNzA1NDM2NzQ3Nn0=', $url);
    }
    public function testBuildsFromInvoiceAndResult(): void
    {
        $invoice = (new InvoiceBuilder(1, 'Factura A'))->clienteCuit('20123456786')->netoGravado(10000)->iva(21, 10000)->importeTotal(12100)->construir();
        $data = $this->decode(QrArca::url($invoice, $this->authorized(1), '30-71234567-1'));
        self::assertSame(['ver' => 1, 'fecha' => '2026-11-20', 'cuit' => 30712345671, 'ptoVta' => 3, 'tipoCmp' => 1, 'nroCmp' => 94, 'importe' => 12100, 'moneda' => 'PES', 'ctz' => 1, 'tipoDocRec' => 80, 'nroDocRec' => 20123456786, 'tipoCodAut' => 'E', 'codAut' => 70417054367476], $data);
    }
    public function testOmitsReceiverForAnonymousFinalConsumer(): void
    {
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->importeTotal(1500.5)->construir();
        $data = $this->decode(QrArca::url($invoice, $this->authorized(11), '30712345671'));
        self::assertArrayNotHasKey('tipoDocRec', $data);
        self::assertArrayNotHasKey('nroDocRec', $data);
        self::assertSame(1500.5, $data['importe']);
    }
    public function testRejectsResultWithoutCae(): void
    {
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->importeTotal(100)->construir();
        $this->expectException(ValidationException::class);
        QrArca::url($invoice, new InvoiceResult('rejected', 'op', 'venta-1'), '30712345671');
    }
    public function testRejectsResultOfAnotherVoucherType(): void
    {
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->importeTotal(100)->construir();
        $this->expectException(ValidationException::class);
        QrArca::url($invoice, $this->authorized(6), '30712345671');
    }
    private function authorized(int $type): InvoiceResult
    {
        return new InvoiceResult('authorized', 'op', 'venta-1', '70417054367476', new DateTimeImmutable('2026-11-30'), 94, 3, $type, new DateTimeImmutable('2026-11-20'));
    }
    /** @return array<string,mixed> */
    private function decode(string $url): array
    {
        self::assertStringStartsWith(QrArca::URL . '?p=', $url);
        return json_decode(base64_decode(substr($url, strlen(QrArca::URL . '?p=')), true), true, flags: JSON_THROW_ON_ERROR);
    }
}
