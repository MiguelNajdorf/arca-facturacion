<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
try {
    $arca = ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__));
    $factura = $arca->facturaB()->clienteCuit('CUIT-INVALIDO')->netoGravado(100)->iva21(10)->importeTotal(121);
    $arca->simular($factura);
} catch (ArcaFacturacion\Exception\ValidationException $error) {
    foreach ($error->errores() as $message) { echo '- ' . $message . PHP_EOL; }
} catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) {
    echo $error->mensajeHumano() . PHP_EOL;
}
