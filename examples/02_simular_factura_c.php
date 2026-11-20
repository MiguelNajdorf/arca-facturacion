<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
try {
    $arca = ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__));
    $factura = $arca->facturaC()->consumidorFinal()->conceptoProductos()->importeTotal(15000);
    echo json_encode($arca->simular($factura), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) { fwrite(STDERR, $error->mensajeHumano() . PHP_EOL); exit(1); }
