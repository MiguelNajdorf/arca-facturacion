<?php
declare(strict_types=1);
namespace ArcaFacturacion\Infrastructure;
use ArcaFacturacion\Invoice\Invoice;
interface ArcaGateway
{
    /** @return array<string,string> */ public function health(): array;
    public function lastVoucher(int $salesPoint, int $voucherType): int;
    /** @return array<string,mixed> */ public function authorize(Invoice $invoice, int $salesPoint, int $voucherNumber): array;
    /** @return array<string,mixed>|null */ public function findVoucher(int $salesPoint, int $voucherType, int $voucherNumber): ?array;
    /** @return list<array<string,mixed>> */ public function salesPoints(): array;
    /** @return list<array<string,mixed>> */ public function receiverVatConditions(string $voucherClass = ''): array;
    /** @return list<array<string,mixed>> */ public function voucherTypes(): array;
}
