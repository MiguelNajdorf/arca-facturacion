<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Exception\ConnectivityException;
use ArcaFacturacion\Infrastructure\ArcaGateway;
use ArcaFacturacion\Invoice\Invoice;
final class FakeArcaGateway implements ArcaGateway
{
    public int $authorizeCalls = 0;
    public bool $failAuthorization = false;
    /** @var array<string,mixed>|null */ public ?array $foundVoucher = null;
    public function health(): array { return ['app_server' => 'OK', 'db_server' => 'OK', 'auth_server' => 'OK']; }
    public function lastVoucher(int $salesPoint, int $voucherType): int { return 40; }
    public function authorize(Invoice $invoice, int $salesPoint, int $voucherNumber): array
    {
        $this->authorizeCalls++;
        if ($this->failAuthorization) { throw new ConnectivityException('Corte simulado.'); }
        return ['status' => 'authorized', 'cae' => '12345678901234', 'cae_expiration' => '2026-08-01', 'voucher_number' => $voucherNumber, 'sales_point' => $salesPoint, 'voucher_type' => $invoice->voucherType, 'date' => '2026-07-20', 'observations' => []];
    }
    public function findVoucher(int $salesPoint, int $voucherType, int $voucherNumber): ?array { return $this->foundVoucher; }
    public function salesPoints(): array { return [['number' => 1, 'type' => 'CAE', 'blocked' => 'N', 'from' => '']]; }
    public function receiverVatConditions(string $voucherClass = ''): array { return [['id' => 5, 'description' => 'Consumidor final', 'voucher_class' => 'C']]; }
    public function voucherTypes(): array { return [['id' => 11, 'description' => 'Factura C', 'valid_from' => '', 'valid_until' => '']]; }
}
