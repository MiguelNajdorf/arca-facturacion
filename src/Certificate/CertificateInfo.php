<?php
declare(strict_types=1);
namespace ArcaFacturacion\Certificate;
use DateTimeImmutable;
use JsonSerializable;
final class CertificateInfo implements JsonSerializable
{
    public function __construct(
        public readonly string $subject,
        public readonly string $issuer,
        public readonly DateTimeImmutable $validFrom,
        public readonly DateTimeImmutable $validUntil,
        public readonly int $daysRemaining,
        public readonly bool $matchesPrivateKey,
        public readonly bool $expiringSoon
    ) {}
    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return ['subject' => $this->subject, 'issuer' => $this->issuer, 'valid_from' => $this->validFrom->format(DATE_ATOM), 'valid_until' => $this->validUntil->format(DATE_ATOM), 'days_remaining' => $this->daysRemaining, 'matches_private_key' => $this->matchesPrivateKey, 'expiring_soon' => $this->expiringSoon];
    }
}
