<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
$failures = [];
$count = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $normalizedPath = str_replace('\\', '/', $path);
    $excluded = ['/vendor/', '/tmp/', '/storage/', '/.phpunit.cache/', '/.phpstan.cache/', '/.gstack/'];
    if (array_filter($excluded, static fn (string $part): bool => str_contains($normalizedPath, $part))) {
        continue;
    }
    $count++;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1';
    exec($command, $output, $status);
    if ($status !== 0) {
        $failures[] = implode(PHP_EOL, $output);
    }
    $output = [];
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL . PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "OK: {$count} archivos PHP sin errores de sintaxis." . PHP_EOL;
