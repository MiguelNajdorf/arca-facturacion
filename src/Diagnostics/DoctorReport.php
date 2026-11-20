<?php
declare(strict_types=1);
namespace ArcaFacturacion\Diagnostics;
use JsonSerializable;
final class DoctorReport implements JsonSerializable
{
    /** @param list<Check> $checks */ public function __construct(public readonly array $checks) {}
    public function exitCode(): int
    {
        foreach ($this->checks as $check) { if ($check->status === 'connection_error') { return 3; } }
        foreach ($this->checks as $check) { if ($check->status === 'error') { return 2; } }
        foreach ($this->checks as $check) { if ($check->status === 'not_checked') { return 2; } }
        foreach ($this->checks as $check) { if ($check->status === 'warning') { return 1; } }
        return 0;
    }
    public function ready(): bool { return $this->exitCode() < 2; }
    public function has(string $label, string $status): bool
    {
        foreach ($this->checks as $check) { if ($check->label === $label && $check->status === $status) { return true; } }
        return false;
    }
    /** @return array<string,mixed> */ public function jsonSerialize(): array { return ['ready' => $this->ready(), 'exit_code' => $this->exitCode(), 'checks' => $this->checks]; }
}
