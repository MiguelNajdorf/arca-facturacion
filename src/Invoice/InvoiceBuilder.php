<?php
declare(strict_types=1);
namespace ArcaFacturacion\Invoice;
use ArcaFacturacion\Support\Cuit;
use DateTimeImmutable;
final class InvoiceBuilder
{
    /** @var array<string,mixed> */
    private array $data;
    public function __construct(int $voucherType, string $label)
    {
        $this->data = ['voucher_type' => $voucherType, 'label' => $label, 'concept' => 1, 'document_type' => 99, 'document_number' => '0', 'receiver_vat_condition' => 5, 'net' => 0.0, 'total' => 0.0, 'vat' => 0.0, 'vat_breakdown' => []];
    }
    public function consumidorFinal(int $vatCondition = 5): self { $copy = clone $this; $copy->data['document_type'] = 99; $copy->data['document_number'] = '0'; $copy->data['receiver_vat_condition'] = $vatCondition; return $copy; }
    public function clienteCuit(string $cuit, int $vatCondition = 1): self { $copy = clone $this; $copy->data['document_type'] = 80; $copy->data['document_number'] = Cuit::normalizar($cuit); $copy->data['receiver_vat_condition'] = $vatCondition; return $copy; }
    public function condicionIvaReceptor(int $id): self { $copy = clone $this; $copy->data['receiver_vat_condition'] = $id; return $copy; }
    public function conceptoProductos(): self { return $this->with('concept', 1); }
    public function conceptoServicios(string $from, string $to, string $due): self { $copy = clone $this; $copy->data['concept'] = 2; $copy->data['service_from'] = new DateTimeImmutable($from); $copy->data['service_to'] = new DateTimeImmutable($to); $copy->data['payment_due'] = new DateTimeImmutable($due); return $copy; }
    public function conceptoProductosYServicios(string $from, string $to, string $due): self { $copy = $this->conceptoServicios($from, $to, $due); $copy->data['concept'] = 3; return $copy; }
    public function importeTotal(float $amount): self { $copy = $this->with('total', $amount); if ((float) $copy->data['net'] === 0.0) { $copy->data['net'] = $amount; } return $copy; }
    public function netoGravado(float $amount): self { return $this->with('net', $amount); }
    public function iva21(float $amount): self { $copy = clone $this; $copy->data['vat'] = $amount; $copy->data['vat_breakdown'][] = ['rate' => 21.0, 'base' => (float) $copy->data['net'], 'amount' => $amount]; return $copy; }
    public function importeNoGravado(float $amount): self { return $this->with('untaxed', $amount); }
    public function importeExento(float $amount): self { return $this->with('exempt', $amount); }
    public function tributos(float $amount): self { return $this->with('taxes', $amount); }
    public function moneda(string $code, float $rate): self { $copy = clone $this; $copy->data['currency'] = strtoupper($code); $copy->data['exchange_rate'] = $rate; return $copy; }
    public function fecha(string $date): self { return $this->with('date', new DateTimeImmutable($date)); }
    /** @param list<array<string,mixed>> $associated */
    public function comprobantesAsociados(array $associated): self { return $this->with('associated', $associated); }
    public function construir(): Invoice
    {
        return new Invoice(
            $this->data['voucher_type'], $this->data['label'], $this->data['concept'], $this->data['document_type'],
            $this->data['document_number'], $this->data['receiver_vat_condition'], (float) $this->data['total'],
            (float) $this->data['net'], (float) ($this->data['untaxed'] ?? 0), (float) ($this->data['exempt'] ?? 0),
            (float) $this->data['vat'], (float) ($this->data['taxes'] ?? 0), $this->data['vat_breakdown'],
            (string) ($this->data['currency'] ?? 'PES'), (float) ($this->data['exchange_rate'] ?? 1),
            $this->data['date'] ?? null, $this->data['service_from'] ?? null, $this->data['service_to'] ?? null,
            $this->data['payment_due'] ?? null, $this->data['associated'] ?? []
        );
    }
    private function with(string $key, mixed $value): self { $copy = clone $this; $copy->data[$key] = $value; return $copy; }
}
