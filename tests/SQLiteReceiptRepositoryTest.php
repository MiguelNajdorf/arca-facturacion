<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Invoice\InvoiceBuilder;
use ArcaFacturacion\Receipt\SQLiteReceiptRepository;
use PHPUnit\Framework\TestCase;
final class SQLiteReceiptRepositoryTest extends TestCase
{
    public function testPersistsAllOperationStates(): void
    {
        $path = sys_get_temp_dir() . '/arca-test-' . bin2hex(random_bytes(6)) . '.sqlite';
        $repository = new SQLiteReceiptRepository($path);
        $invoice = (new InvoiceBuilder(11, 'Factura C'))->importeTotal(100)->construir();
        $record = $repository->createPending('op-1', 'venta-0001', $invoice->fingerprint(), $invoice, 1);
        self::assertSame('pending', $record->status);
        $record = $repository->markProcessing('op-1', 7);
        self::assertSame(7, $record->voucherNumber);
        $record = $repository->markAuthorized('op-1', ['cae' => '123', 'cae_expiration' => '2026-08-01']);
        self::assertSame('authorized', $record->status);
        self::assertSame('123', $repository->findByIdempotencyKey('venta-0001')?->cae);
        @unlink($path); @unlink($path . '-wal'); @unlink($path . '-shm');
    }
}
