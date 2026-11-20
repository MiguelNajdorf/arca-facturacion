<?php
declare(strict_types=1);
namespace ArcaFacturacion\Invoice;
use DateTimeImmutable;
use JsonSerializable;
final class Invoice implements JsonSerializable
{
    /**
     * @param list<array{rate: float, base: float, amount: float}> $vatBreakdown
     * @param list<array<string, mixed>> $associated
     */
    public function __construct(
        public readonly int $voucherType,
        public readonly string $label,
        public readonly int $concept,
        public readonly int $documentType,
        public readonly string $documentNumber,
        public readonly int $receiverVatCondition,
        public readonly float $total,
        public readonly float $net,
        public readonly float $untaxed = 0.0,
        public readonly float $exempt = 0.0,
        public readonly float $vat = 0.0,
        public readonly float $taxes = 0.0,
        public readonly array $vatBreakdown = [],
        public readonly string $currency = 'PES',
        public readonly float $exchangeRate = 1.0,
        public readonly ?DateTimeImmutable $date = null,
        public readonly ?DateTimeImmutable $serviceFrom = null,
        public readonly ?DateTimeImmutable $serviceTo = null,
        public readonly ?DateTimeImmutable $paymentDue = null,
        public readonly array $associated = []
    ) {}
    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'voucher_type' => $this->voucherType, 'label' => $this->label, 'concept' => $this->concept,
            'document_type' => $this->documentType, 'document_number' => $this->documentNumber,
            'receiver_vat_condition' => $this->receiverVatCondition, 'total' => $this->total,
            'net' => $this->net, 'untaxed' => $this->untaxed, 'exempt' => $this->exempt,
            'vat' => $this->vat, 'taxes' => $this->taxes, 'vat_breakdown' => $this->vatBreakdown,
            'currency' => $this->currency, 'exchange_rate' => $this->exchangeRate,
            'date' => ($this->date ?? new DateTimeImmutable('today'))->format('Y-m-d'),
            'service_from' => $this->serviceFrom?->format('Y-m-d'), 'service_to' => $this->serviceTo?->format('Y-m-d'),
            'payment_due' => $this->paymentDue?->format('Y-m-d'), 'associated' => $this->associated,
        ];
    }
    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->jsonSerialize(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
