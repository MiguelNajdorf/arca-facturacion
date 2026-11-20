<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
try {
    $arca = ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__));
    $nota = $arca->notaCreditoC()->consumidorFinal()->importeTotal(1500)->comprobantesAsociados([['type' => 11, 'sales_point' => 1, 'number' => 1, 'date' => date('Y-m-d')]]);
    echo json_encode($arca->simular($nota), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) { fwrite(STDERR, $error->mensajeHumano() . PHP_EOL); exit(1); }
