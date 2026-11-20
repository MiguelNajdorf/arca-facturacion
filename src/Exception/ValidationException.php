<?php
declare(strict_types=1);
namespace ArcaFacturacion\Exception;
final class ValidationException extends ArcaFacturacionException
{
    /** @param list<string> $errors */
    public function __construct(private readonly array $errors) { parent::__construct(implode(PHP_EOL, $errors), 'validation_error'); }
    /** @return list<string> */
    public function errores(): array { return $this->errors; }
}
