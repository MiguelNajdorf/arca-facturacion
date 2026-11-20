<?php
declare(strict_types=1);
namespace ArcaFacturacion\Receipt;
use JsonSerializable;
final class ReceiptRecord implements JsonSerializable
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed>|null $response
     * @param list<array<string, mixed>> $errors
     * @param list<string> $observations
     */
    public function __construct(
        public readonly string $operationId, public readonly string $idempotencyKey, public readonly string $fingerprint,
        public readonly string $status, public readonly int $voucherType, public readonly int $salesPoint,
        public readonly float $amount, public readonly array $request, public readonly ?int $voucherNumber = null,
        public readonly ?string $cae = null, public readonly ?string $caeExpiration = null,
        public readonly ?array $response = null, public readonly array $errors = [], public readonly array $observations = [],
        public readonly string $createdAt = '', public readonly string $updatedAt = ''
    ) {}
    /** @return array<string,mixed> */ public function jsonSerialize(): array { return get_object_vars($this); }
}
