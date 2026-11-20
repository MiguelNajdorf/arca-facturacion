<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
use ArcaFacturacion\Exception\StorageException;
final class Lock
{
    /** @var resource|null */ private $handle;
    public function __construct(string $directory, string $name)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new StorageException('No se pudo crear la carpeta de bloqueos.'); }
        $this->handle = fopen($directory . '/' . hash('sha256', $name) . '.lock', 'c+');
        if ($this->handle === false || !flock($this->handle, LOCK_EX)) { throw new StorageException('No se pudo bloquear la operacion.'); }
    }
    public function release(): void { if (is_resource($this->handle)) { flock($this->handle, LOCK_UN); fclose($this->handle); } $this->handle = null; }
    public function __destruct() { $this->release(); }
}
