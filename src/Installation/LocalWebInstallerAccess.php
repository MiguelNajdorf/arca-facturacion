<?php
declare(strict_types=1);

namespace ArcaFacturacion\Installation;

final class LocalWebInstallerAccess
{
    /** @param array<string,mixed> $server */
    public function isAllowed(array $server): bool
    {
        $remote = strtolower(trim((string) ($server['REMOTE_ADDR'] ?? '')));
        if (!$this->isLoopback($remote)) { return false; }

        $rawHost = trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
        $host = strtolower((string) parse_url('http://' . $rawHost, PHP_URL_HOST));
        $host = trim($host, '[]');
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) { return true; }
        return str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }

    private function isLoopback(string $address): bool
    {
        if ($address === '::1' || $address === '0:0:0:0:0:0:0:1') { return true; }
        if (str_starts_with($address, '::ffff:')) { $address = substr($address, 7); }
        return preg_match('/^127(?:\.\d{1,3}){3}$/', $address) === 1;
    }
}
