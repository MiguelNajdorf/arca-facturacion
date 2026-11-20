<?php
declare(strict_types=1);
namespace ArcaFacturacion\Diagnostics;
use JsonSerializable;
final class Check implements JsonSerializable
{
    public function __construct(public readonly string $section, public readonly string $label, public readonly string $status, public readonly string $message = '') {}
    public function displayStatus(): string
    {
        return ['ok' => 'OK', 'warning' => 'ADVERTENCIA', 'error' => 'ERROR', 'connection_error' => 'ERROR', 'not_checked' => 'NO COMPROBADO', 'info' => 'INFO'][$this->status] ?? strtoupper($this->status);
    }
    /** @return array<string,string> */ public function jsonSerialize(): array { return get_object_vars($this); }
}
