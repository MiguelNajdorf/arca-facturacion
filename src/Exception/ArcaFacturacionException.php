<?php
declare(strict_types=1);
namespace ArcaFacturacion\Exception;
use RuntimeException;
use Throwable;
class ArcaFacturacionException extends RuntimeException
{
    public function __construct(string $message, private readonly ?string $technicalCode = null, private readonly ?string $technicalDetail = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
    public function mensajeHumano(): string { return $this->getMessage(); }
    public function codigo(): ?string { return $this->technicalCode; }
    public function detalleTecnico(): ?string { return $this->technicalDetail; }
}
