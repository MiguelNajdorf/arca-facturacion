<?php
declare(strict_types=1);
namespace ArcaFacturacion\Installation;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Support\AtomicFile;
final class InstallationStateStore
{
    private string $path;
    public function __construct(private readonly string $root)
    {
        $this->path = $root . '/storage/instalacion.json';
    }
    /** @return array<string,mixed> */
    public function read(): array
    {
        if (!file_exists($this->path)) {
            $recovered = (new LegacyInstallationRecovery($this->root))->recover();
            if ($recovered === null) { return ['version' => 1, 'active_environment' => null, 'environments' => []]; }
            AtomicFile::writeNew($this->path, json_encode($recovered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, 0600);
            return $recovered;
        }
        if (!is_readable($this->path)) { throw new ConfigurationException('El estado de instalacion existe pero no puede leerse. No se modifico ningun archivo: ' . $this->path); }
        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($decoded) || !is_array($decoded['environments'] ?? null)) { throw new ConfigurationException('El estado de instalacion existe pero es invalido. No se reemplazara automaticamente: ' . $this->path); }
        return $decoded;
    }
    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function saveEnvironment(string $environment, array $values, bool $activate = true, bool $recoverIfMissing = true): array
    {
        $state = $recoverIfMissing ? $this->read() : $this->readPersisted();
        $existing = is_array($state['environments'][$environment] ?? null) ? $state['environments'][$environment] : [];
        $state['environments'][$environment] = array_merge($existing, $values, ['environment' => $environment, 'updated_at' => date(DATE_ATOM)]);
        if ($activate) { $state['active_environment'] = $environment; }
        $this->write($state);
        return $this->environment($environment) ?? [];
    }
    public function mark(string $environment, string $field, bool $value = true): void
    {
        $allowed = ['certificate_obtained', 'certificate_installed', 'service_authorized', 'connection_validated', 'simulation_completed', 'first_emission_completed'];
        if (!in_array($field, $allowed, true)) { throw new ConfigurationException('El avance de instalacion indicado no existe.'); }
        $this->saveEnvironment($environment, [$field => $value], false);
    }
    public function activate(string $environment): void
    {
        $state = $this->read();
        if (!is_array($state['environments'][$environment] ?? null)) { throw new ConfigurationException('No existe una preparacion para el ambiente ' . $environment . '.'); }
        $state['active_environment'] = $environment;
        $this->write($state);
    }
    public function activeEnvironment(): ?string
    {
        $value = $this->read()['active_environment'] ?? null;
        return is_string($value) && in_array($value, ['homologacion', 'produccion'], true) ? $value : null;
    }
    /** @return array<string,mixed>|null */
    public function environment(?string $environment = null): ?array
    {
        $state = $this->read();
        $environment ??= is_string($state['active_environment'] ?? null) ? $state['active_environment'] : null;
        if ($environment === null || !is_array($state['environments'][$environment] ?? null)) { return null; }
        $item = $state['environments'][$environment];
        $item['key_generated'] = is_file((string) ($item['key_path'] ?? ''));
        $item['csr_generated'] = is_file((string) ($item['csr_path'] ?? ''));
        $item['certificate_installed'] = (bool) ($item['certificate_installed'] ?? false) && is_file((string) ($item['certificate_path'] ?? ''));
        return $item;
    }
    /** @return list<array{key:string,label:string,status:string}> */
    public function checklist(?string $environment = null): array
    {
        $item = $this->environment($environment) ?? [];
        $name = $environment ?? (string) ($item['environment'] ?? 'el ambiente');
        $checks = [
            'environment_ready' => ['Entorno preparado', (bool) ($item['configured'] ?? false)],
            'configuration_saved' => ['Configuracion guardada', (bool) ($item['configured'] ?? false)],
            'key_generated' => ['Clave privada generada', (bool) ($item['key_generated'] ?? false)],
            'csr_generated' => ['CSR generado', (bool) ($item['csr_generated'] ?? false)],
            'certificate_obtained' => ['Certificado obtenido desde ARCA', (bool) ($item['certificate_obtained'] ?? false)],
            'certificate_installed' => ['Certificado instalado', (bool) ($item['certificate_installed'] ?? false)],
            'service_authorized' => ['Servicio WSFE autorizado', (bool) ($item['service_authorized'] ?? false)],
            'connection_validated' => ['Conexion validada', (bool) ($item['connection_validated'] ?? false)],
            'simulation_completed' => ['Primera simulacion', (bool) ($item['simulation_completed'] ?? false)],
            'first_emission_completed' => ['Primera emision en ' . $name, (bool) ($item['first_emission_completed'] ?? false)],
        ];
        $result = [];
        foreach ($checks as $key => [$label, $done]) { $result[] = ['key' => $key, 'label' => $label, 'status' => $done ? 'ok' : 'pending']; }
        return $result;
    }
    public function currentStep(?string $environment = null): int
    {
        $item = $this->environment($environment);
        if ($item === null || !(bool) ($item['configured'] ?? false)) { return 1; }
        if (!(bool) ($item['key_generated'] ?? false) || !(bool) ($item['csr_generated'] ?? false)) { return 2; }
        if (!(bool) ($item['certificate_installed'] ?? false)) { return 3; }
        if (!(bool) ($item['service_authorized'] ?? false)) { return 4; }
        if (!(bool) ($item['connection_validated'] ?? false) || !(bool) ($item['simulation_completed'] ?? false)) { return 5; }
        return 6;
    }
    /** @param array<string,mixed> $state */
    private function write(array $state): void
    {
        AtomicFile::write($this->path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, 0600);
    }
    /** @return array<string,mixed> */
    private function readPersisted(): array
    {
        if (!file_exists($this->path)) { return ['version' => 1, 'active_environment' => null, 'environments' => []]; }
        if (!is_readable($this->path)) { throw new ConfigurationException('El estado de instalacion existe pero no puede leerse. No se modifico ningun archivo: ' . $this->path); }
        $decoded = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($decoded) || !is_array($decoded['environments'] ?? null)) { throw new ConfigurationException('El estado de instalacion existe pero es invalido. No se reemplazara automaticamente: ' . $this->path); }
        return $decoded;
    }
}
