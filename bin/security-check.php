<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$skip = ['/vendor/', '/tmp/', '/storage/', '/.git/', '/.gstack/', '/.phpunit.cache/', '/.phpstan.cache/'];
$patterns = [
    '/-----BEGIN (?:RSA |EC |ENCRYPTED )?PRIVATE KEY-----/' => 'clave privada PEM',
    '/ARCA_KEY_PASSPHRASE\s*=\s*[^\s#]+/' => 'frase secreta configurada',
];
$failures = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getSize() > 1048576) { continue; }
    $path = str_replace('\\', '/', $file->getPathname());
    if (array_filter($skip, static fn (string $part): bool => str_contains($path, $part))) { continue; }
    if (basename($path) === '.env') { $failures[] = '.env existe en el proyecto; confirma que permanece ignorado por Git.'; continue; }
    $content = @file_get_contents($file->getPathname());
    if (!is_string($content)) { continue; }
    foreach ($patterns as $pattern => $label) { if (preg_match($pattern, $content)) { $failures[] = $path . ': posible ' . $label; } }
}
if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "OK: no se detectaron secretos o claves privadas en archivos publicables.\n";
