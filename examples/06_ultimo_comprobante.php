<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$type = (int) ($argv[1] ?? 11);
try { echo 'Ultimo numero autorizado: ' . ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__))->ultimoComprobante($type) . PHP_EOL; }
catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) { fwrite(STDERR, $error->mensajeHumano() . PHP_EOL); exit(1); }
