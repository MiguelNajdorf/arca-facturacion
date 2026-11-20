<?php
declare(strict_types=1);
namespace ArcaFacturacion\Invoice;
use DateTimeImmutable;
use JsonSerializable;
final class InvoiceResult implements JsonSerializable
{
    /**
     * @param list<string> $observations
     * @param list<InvoiceError> $errors
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        private readonly string $status, private readonly string $operationId, private readonly string $idempotencyKey,
        private readonly ?string $cae = null, private readonly ?DateTimeImmutable $caeExpiration = null,
        private readonly ?int $voucherNumber = null, private readonly ?int $salesPoint = null,
        private readonly ?int $voucherType = null, private readonly ?DateTimeImmutable $date = null,
        private readonly array $observations = [], private readonly array $errors = [],
        private readonly bool $reused = false, private readonly ?array $raw = null
    ) {}
    public function fueAutorizado(): bool { return $this->status === 'authorized'; }
    public function fueRechazado(): bool { return $this->status === 'rejected'; }
    public function esIncierto(): bool { return $this->status === 'unknown'; }
    public function esReutilizado(): bool { return $this->reused; }
    public function cae(): ?string { return $this->cae; }
    public function vencimientoCae(): ?DateTimeImmutable { return $this->caeExpiration; }
    public function numeroComprobante(): ?int { return $this->voucherNumber; }
    public function puntoDeVenta(): ?int { return $this->salesPoint; }
    public function tipoComprobante(): ?int { return $this->voucherType; }
    public function fecha(): ?DateTimeImmutable { return $this->date; }
    /** @return list<string> */ public function observaciones(): array { return $this->observations; }
    /** @return list<InvoiceError> */ public function errores(): array { return $this->errors; }
    /** @return array<string,mixed>|null */ public function respuestaCruda(): ?array { return $this->raw; }
    public function operationId(): string { return $this->operationId; }
    public function estado(): string { return $this->status; }
    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return ['status' => $this->status, 'operation_id' => $this->operationId, 'idempotency_key' => $this->idempotencyKey, 'reused' => $this->reused, 'cae' => $this->cae, 'cae_expiration' => $this->caeExpiration?->format('Y-m-d'), 'voucher_number' => $this->voucherNumber, 'sales_point' => $this->salesPoint, 'voucher_type' => $this->voucherType, 'date' => $this->date?->format('Y-m-d'), 'observations' => $this->observations, 'errors' => $this->errors];
    }
    /** @return array<string,mixed> */ public function toArray(): array { return $this->jsonSerialize(); }
    public function toJson(): string { return json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
}
