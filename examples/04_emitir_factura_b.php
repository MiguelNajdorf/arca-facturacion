<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
if (getenv('ARCA_EXAMPLE_EMIT') !== '1') { exit("Ejemplo bloqueado. Define ARCA_EXAMPLE_EMIT=1 solo con datos fiscales correctos.\n"); }
try {
    $arca = ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__));
    $factura = $arca->facturaB()->clienteCuit('30712345671', 1)->netoGravado(10000)->iva21(2100)->importeTotal(12100);
    echo $arca->emitir($factura, 'ejemplo-b-' . date('Ymd-His'))->toJson() . PHP_EOL;
} catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) { fwrite(STDERR, $error->mensajeHumano() . PHP_EOL); exit(1); }
