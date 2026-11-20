<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
final class Logger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
    public function __construct(private readonly string $path, private readonly string $minimum = 'warning') {}
    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 100) < (self::LEVELS[$this->minimum] ?? 30)) { return; }
        foreach ($context as $key => $value) { if (preg_match('/token|sign|secret|pass|key|clave|cert/i', (string) $key)) { $context[$key] = '[OCULTO]'; } }
        $directory = dirname($this->path);
        if (!is_dir($directory)) { @mkdir($directory, 0700, true); }
        @file_put_contents($this->path, json_encode(['time' => date(DATE_ATOM), 'level' => $level, 'message' => $message, 'context' => $context], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
