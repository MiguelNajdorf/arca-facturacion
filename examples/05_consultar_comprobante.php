<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$type = (int) ($argv[1] ?? 11); $number = (int) ($argv[2] ?? 0);
if ($number < 1) { exit("Uso: php examples/05_consultar_comprobante.php TIPO NUMERO\n"); }
try { echo json_encode(ArcaFacturacion\Arca::desdeEntorno(dirname(__DIR__))->consultarComprobante($type, $number), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL; }
catch (ArcaFacturacion\Exception\ArcaFacturacionException $error) { fwrite(STDERR, $error->mensajeHumano() . PHP_EOL); exit(1); }
