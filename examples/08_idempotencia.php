<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (getenv('ARCA_EXAMPLE_EMIT') !== '1') { exit("Este ejemplo emite. Define ARCA_EXAMPLE_EMIT=1 despues de probar en homologacion.\n"); }
$arca = ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__));
$factura = $arca->facturaC()->consumidorFinal()->importeTotal(100);
$key = 'venta-sistema-000123';
$primero = $arca->emitir($factura, $key);
$reintento = $arca->emitir($factura, $key);
echo 'Primer CAE: ' . $primero->cae() . PHP_EOL;
echo 'El reintento reutilizo el resultado: ' . ($reintento->esReutilizado() ? 'si' : 'no') . PHP_EOL;
