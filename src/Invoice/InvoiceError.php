<?php
declare(strict_types=1);
namespace ArcaFacturacion\Invoice;
use JsonSerializable;
final class InvoiceError implements JsonSerializable
{
    public function __construct(public readonly string $message, public readonly ?string $code = null, public readonly ?string $technicalDetail = null) {}
    /** @return array<string,mixed> */ public function jsonSerialize(): array { return ['code' => $this->code, 'message' => $this->message]; }
}
