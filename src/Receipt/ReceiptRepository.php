<?php
declare(strict_types=1);
namespace ArcaFacturacion\Receipt;
use ArcaFacturacion\Invoice\Invoice;
interface ReceiptRepository
{
    public function findByIdempotencyKey(string $key): ?ReceiptRecord;
    public function findByOperationId(string $operationId): ?ReceiptRecord;
    public function createPending(string $operationId, string $key, string $fingerprint, Invoice $invoice, int $salesPoint): ReceiptRecord;
    public function markProcessing(string $operationId, int $voucherNumber): ReceiptRecord;
    /**
     * @param array<string, mixed> $response
     * @param list<string> $observations
     */
    public function markAuthorized(string $operationId, array $response, array $observations = []): ReceiptRecord;
    /**
     * @param list<array<string, mixed>> $errors
     * @param array<string, mixed>|null $response
     */
    public function markRejected(string $operationId, array $errors, ?array $response = null): ReceiptRecord;
    /** @param list<array<string,mixed>> $errors */
    public function markUnknown(string $operationId, array $errors): ReceiptRecord;
    /** @return list<ReceiptRecord> */
    public function recent(int $limit = 20): array;
}
