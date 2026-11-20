<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (getenv('ARCA_EXAMPLE_EMIT') !== '1') { exit("Ejemplo bloqueado. Simula primero y define ARCA_EXAMPLE_EMIT=1 para emitir conscientemente.\n"); }
try {
    $arca = ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__));
    $factura = $arca->facturaC()->consumidorFinal()->conceptoProductos()->importeTotal(15000);
    echo $arca->emitir($factura, 'ejemplo-c-' . date('Ymd-His'))->toJson() . PHP_EOL;
} catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) { fwrite(STDERR, $error->mensajeHumano() . PHP_EOL); exit(1); }
