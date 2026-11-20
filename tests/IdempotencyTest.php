<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Arca;
use ArcaFacturacion\Config\ArcaConfig;
use ArcaFacturacion\Exception\DuplicateInvoiceException;
use ArcaFacturacion\Receipt\SQLiteReceiptRepository;
use PHPUnit\Framework\TestCase;
final class IdempotencyTest extends TestCase
{
    public function testReusesAuthorizedResultWithoutSecondSend(): void
    {
        [$arca, $gateway] = $this->arca();
        $invoice = $arca->facturaC()->consumidorFinal()->importeTotal(100);
        $first = $arca->emitir($invoice, 'venta-0001');
        $second = $arca->emitir($invoice, 'venta-0001');
        self::assertTrue($first->fueAutorizado());
        self::assertTrue($second->esReutilizado());
        self::assertSame(1, $gateway->authorizeCalls);
    }
    public function testRejectsSameKeyWithDifferentPayload(): void
    {
        [$arca] = $this->arca();
        $arca->emitir($arca->facturaC()->importeTotal(100), 'venta-0002');
        $this->expectException(DuplicateInvoiceException::class);
        $arca->emitir($arca->facturaC()->importeTotal(101), 'venta-0002');
    }
    public function testRecoversUnknownOperationByConsultingArca(): void
    {
        [$arca, $gateway] = $this->arca();
        $gateway->failAuthorization = true;
        $unknown = $arca->emitir($arca->facturaC()->importeTotal(100), 'venta-0003');
        self::assertTrue($unknown->esIncierto());
        $gateway->foundVoucher = ['status' => 'authorized', 'cae' => '999', 'cae_expiration' => '2026-08-01', 'voucher_number' => 41, 'sales_point' => 1, 'voucher_type' => 11, 'date' => '2026-07-20', 'observations' => []];
        self::assertTrue($arca->recuperar($unknown->operationId())->fueAutorizado());
    }
    /** @return array{Arca,FakeArcaGateway} */
    private function arca(): array
    {
        $directory = sys_get_temp_dir() . '/arca-' . bin2hex(random_bytes(6)); @mkdir($directory, 0700, true);
        $config = new ArcaConfig('homologacion', '20123456786', 1, $directory . '/cert.crt', $directory . '/key.pem', $directory, debug: true);
        $gateway = new FakeArcaGateway();
        return [Arca::crear($config, $gateway, new SQLiteReceiptRepository($directory . '/test.sqlite')), $gateway];
    }
}
