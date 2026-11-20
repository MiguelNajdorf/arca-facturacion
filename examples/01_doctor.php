<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$report = (new ArcaFacturacion\Diagnostics\Doctor(dirname(__DIR__)))->run(false);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report->exitCode());
