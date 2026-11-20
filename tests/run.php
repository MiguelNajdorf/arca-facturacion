<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
use ArcaFacturacion\Invoice\InvoiceBuilder;
use ArcaFacturacion\Invoice\InvoiceValidator;
use ArcaFacturacion\Support\Cuit;
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void { $checks++; if (!$condition) { throw new RuntimeException($message); } };
$assert(Cuit::esValido('20123456786'), 'CUIT valido');
$assert(!Cuit::esValido('20123456780'), 'CUIT invalido');
$invoice = (new InvoiceBuilder(11, 'Factura C'))->consumidorFinal()->importeTotal(15000)->construir();
(new InvoiceValidator())->validate($invoice);
$assert($invoice->total === 15000.0, 'Factura C simple');
echo "OK: {$checks} comprobaciones locales.\n";
