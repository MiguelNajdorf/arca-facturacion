<?php
declare(strict_types=1);
namespace ArcaFacturacion\Application;
use ArcaFacturacion\Config\ArcaConfig;
use ArcaFacturacion\Exception\AuthenticationException;
use ArcaFacturacion\Exception\ConnectivityException;
use ArcaFacturacion\Exception\DuplicateInvoiceException;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Infrastructure\ArcaGateway;
use ArcaFacturacion\Infrastructure\RequestMapper;
use ArcaFacturacion\Invoice\Invoice;
use ArcaFacturacion\Invoice\InvoiceError;
use ArcaFacturacion\Invoice\InvoiceResult;
use ArcaFacturacion\Invoice\InvoiceValidator;
use ArcaFacturacion\Receipt\ReceiptRecord;
use ArcaFacturacion\Receipt\ReceiptRepository;
use ArcaFacturacion\Support\Lock;
use ArcaFacturacion\Support\Logger;
use ArcaFacturacion\Support\OperationId;
use DateTimeImmutable;
use Throwable;
final class InvoiceService
{
    public function __construct(
        private readonly ArcaConfig $config, private readonly ArcaGateway $gateway,
        private readonly ReceiptRepository $repository, private readonly InvoiceValidator $validator,
        private readonly RequestMapper $mapper, private readonly Logger $logger
    ) {}
    /** @return array<string,mixed> */
    public function simulate(Invoice $invoice, bool $advanced = false): array
    {
        $this->validator->validate($invoice);
        $request = $this->mapper->map($invoice, $this->config->salesPoint, 1);
        return [
            'valid' => true, 'sent' => false, 'type' => $invoice->label, 'concept' => $invoice->concept,
            'sales_point' => $this->config->salesPoint, 'document' => $invoice->documentType === 99 ? 'Consumidor final' : $invoice->documentNumber,
            'total' => $invoice->total, 'message' => 'Comprobante preparado correctamente. No se envio ninguna solicitud a ARCA.',
            'technical_payload' => $advanced ? $request : null,
        ];
    }
    public function issue(Invoice $invoice, string $idempotencyKey): InvoiceResult
    {
        $this->validator->validate($invoice);
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $idempotencyKey)) { throw new ValidationException(['La clave de idempotencia debe tener entre 8 y 128 caracteres seguros.']); }
        $idempotencyLock = new Lock($this->config->locksPath(), 'idempotency-' . $idempotencyKey);
        try {
            $existing = $this->repository->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                if (!hash_equals($existing->fingerprint, $invoice->fingerprint())) { throw new DuplicateInvoiceException('La clave de idempotencia ya fue usada con una factura diferente.'); }
                return $this->result($existing, true);
            }
            $operationId = OperationId::create();
            $record = $this->repository->createPending($operationId, $idempotencyKey, $invoice->fingerprint(), $invoice, $this->config->salesPoint);
            $this->logger->log('info', 'Intento de emision registrado.', ['operation_id' => $operationId, 'status' => 'pending']);
            $fiscalLock = new Lock($this->config->locksPath(), implode('-', ['fiscal', $this->config->environment, $this->config->cuit, $this->config->salesPoint, $invoice->voucherType]));
            try {
                try { $number = $this->gateway->lastVoucher($this->config->salesPoint, $invoice->voucherType) + 1; }
                catch (Throwable $error) {
                    $record = $this->repository->markRejected($operationId, [['code' => $this->code($error), 'message' => $error->getMessage()]]);
                    throw $error;
                }
                $record = $this->repository->markProcessing($operationId, $number);
                try { $response = $this->gateway->authorize($invoice, $this->config->salesPoint, $number); }
                catch (Throwable $error) {
                    $record = $this->repository->markUnknown($operationId, [['code' => $this->code($error), 'message' => 'No se pudo confirmar si ARCA proceso la solicitud. Ejecuta operaciones:recuperar.']]);
                    $this->logger->log('error', 'Operacion en estado incierto.', ['operation_id' => $operationId, 'status' => 'unknown', 'error_code' => $this->code($error)]);
                    if ($error instanceof ConnectivityException || $error instanceof AuthenticationException) { return $this->result($record); }
                    throw $error;
                }
                if (($response['status'] ?? '') === 'authorized') { $record = $this->repository->markAuthorized($operationId, $response, $response['observations'] ?? []); }
                else { $record = $this->repository->markRejected($operationId, $response['errors'] ?? [['code' => null, 'message' => 'ARCA rechazo el comprobante.']], $response); }
                return $this->result($record);
            } finally { $fiscalLock->release(); }
        } finally { $idempotencyLock->release(); }
    }
    public function recover(string $operationId): InvoiceResult
    {
        $record = $this->repository->findByOperationId($operationId);
        if ($record === null) { throw new ValidationException(['No existe una operacion con ese identificador.']); }
        if (!in_array($record->status, ['processing', 'unknown'], true) || $record->voucherNumber === null) { return $this->result($record, true); }
        $found = $this->gateway->findVoucher($record->salesPoint, $record->voucherType, $record->voucherNumber);
        if ($found === null) { return $this->result($record, true); }
        return $this->result($this->repository->markAuthorized($operationId, $found, $found['observations'] ?? []), true);
    }
    /** @return list<ReceiptRecord> */ public function recent(int $limit = 20): array { return $this->repository->recent($limit); }
    private function result(ReceiptRecord $record, bool $reused = false): InvoiceResult
    {
        $errors = array_map(static fn (array $item): InvoiceError => new InvoiceError((string) ($item['message'] ?? 'Error sin detalle'), isset($item['code']) ? (string) $item['code'] : null), $record->errors);
        $date = $record->response['date'] ?? $record->request['date'] ?? null;
        return new InvoiceResult($record->status, $record->operationId, $record->idempotencyKey, $record->cae, $record->caeExpiration ? new DateTimeImmutable($record->caeExpiration) : null, $record->voucherNumber, $record->salesPoint, $record->voucherType, $date ? new DateTimeImmutable((string) $date) : null, $record->observations, $errors, $reused, $this->config->debug ? $record->response : null);
    }
    private function code(Throwable $error): ?string { return method_exists($error, 'codigo') ? $error->codigo() : null; }
}
