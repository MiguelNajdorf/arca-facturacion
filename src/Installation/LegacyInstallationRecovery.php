<?php
declare(strict_types=1);
namespace ArcaFacturacion\Installation;
use ArcaFacturacion\Config\ConfigLoader;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Support\Cuit;
use ArcaFacturacion\Support\PathGuard;
final class LegacyInstallationRecovery
{
    public function __construct(private readonly string $root) {}
    /** @return array<string,mixed>|null */
    public function recover(): ?array
    {
        $sources = $this->configurationSources();
        if ($sources === []) {
            $artifacts = $this->artifactPaths();
            if ($artifacts !== []) {
                throw new ConfigurationException('Encontramos archivos de una instalacion anterior, pero no una configuracion que permita asociarlos con seguridad: ' . implode(', ', $artifacts) . '. No se modifico nada. Revisa las rutas y vuelve a ejecutar instalacion:estado.');
            }
            return null;
        }
        $byEnvironment = [];
        $active = null;
        foreach ($sources as $source => $values) {
            $environment = strtolower((string) ($values['ARCA_ENVIRONMENT'] ?? 'homologacion'));
            if (!in_array($environment, ['homologacion', 'produccion'], true)) {
                throw new ConfigurationException('La configuracion heredada ' . $this->relative($source) . ' tiene un ambiente desconocido. No se modifico ningun archivo.');
            }
            $candidate = $this->candidate($environment, $source, $values);
            if (isset($byEnvironment[$environment]) && $this->signature($byEnvironment[$environment]) !== $this->signature($candidate)) {
                throw new ConfigurationException('Encontramos configuraciones heredadas incompatibles para ' . $environment . '. No se modifico ningun archivo. Revisa .env y storage/configuracion/' . $environment . '.env antes de continuar.');
            }
            if (isset($byEnvironment[$environment])) {
                $candidate['recovered_files'] = array_values(array_unique(array_merge($byEnvironment[$environment]['recovered_files'] ?? [], $candidate['recovered_files'] ?? [])));
            }
            if (!isset($byEnvironment[$environment]) || str_contains(str_replace('\\', '/', $source), '/storage/configuracion/')) {
                $byEnvironment[$environment] = $candidate;
            } else {
                $byEnvironment[$environment]['recovered_files'] = $candidate['recovered_files'];
            }
            if ($source === $this->root . '/.env') { $active = $environment; }
        }
        if ($active === null) {
            if (count($byEnvironment) !== 1) {
                throw new ConfigurationException('Encontramos varios perfiles heredados y no hay un .env que indique cual estaba activo. No se modifico ningun archivo. Ejecuta instalacion:estado --environment=homologacion o produccion despues de revisar los perfiles.');
            }
            $active = (string) array_key_first($byEnvironment);
        }
        return ['version' => 1, 'active_environment' => $active, 'environments' => $byEnvironment, 'recovered_from_legacy' => true, 'recovered_at' => date(DATE_ATOM)];
    }
    /** @return array<string,array<string,string>> */
    private function configurationSources(): array
    {
        $paths = [];
        if (is_file($this->root . '/.env')) { $paths[] = $this->root . '/.env'; }
        foreach (glob($this->root . '/storage/configuracion/*.env') ?: [] as $path) { if (is_file($path)) { $paths[] = $path; } }
        $loader = new ConfigLoader($this->root);
        $result = [];
        foreach (array_unique($paths) as $path) {
            $values = $loader->parse((string) file_get_contents($path));
            if (array_filter(array_keys($values), static fn (string $key): bool => str_starts_with($key, 'ARCA_')) !== []) { $result[$path] = $values; }
        }
        return $result;
    }
    /**
     * @param array<string,string> $values
     * @return array<string,mixed>
     */
    private function candidate(string $environment, string $source, array $values): array
    {
        $cuit = Cuit::normalizar((string) ($values['ARCA_CUIT'] ?? ''));
        $point = filter_var($values['ARCA_POINT_OF_SALE'] ?? null, FILTER_VALIDATE_INT);
        $keyValue = trim((string) ($values['ARCA_KEY_PATH'] ?? ''));
        $certificateValue = trim((string) ($values['ARCA_CERT_PATH'] ?? ''));
        if (!Cuit::esValido($cuit) || $point === false || $point < 1 || $keyValue === '' || $certificateValue === '') {
            throw new ConfigurationException('La configuracion heredada ' . $this->relative($source) . ' esta incompleta o es invalida. No se modifico ningun archivo.');
        }
        $key = PathGuard::absolute($keyValue, $this->root);
        $certificate = PathGuard::absolute($certificateValue, $this->root);
        $storageValue = (string) ($values['ARCA_STORAGE_PATH'] ?? 'storage');
        $storage = PathGuard::absolute($storageValue, $this->root);
        $csr = dirname($key) . '/solicitud.csr';
        if (!is_file($csr)) {
            $matches = $this->files($storage, ['csr']);
            if (count($matches) === 1) { $csr = $matches[0]; }
            elseif (count($matches) > 1) { throw new ConfigurationException('Encontramos varios CSR posibles para ' . $environment . ' y no podemos elegir uno sin riesgo. No se modifico ningun archivo.'); }
        }
        $database = $storage . '/arca-' . $environment . '.sqlite';
        if (!is_file($database)) {
            $matches = $this->files($storage, ['sqlite', 'sqlite3', 'db']);
            $named = array_values(array_filter($matches, static fn (string $path): bool => str_contains(strtolower(basename($path)), $environment)));
            if (count($named) === 1) { $database = $named[0]; }
            elseif (count($matches) === 1) { $database = $matches[0]; }
            elseif (count($matches) > 1) { throw new ConfigurationException('Encontramos varias bases SQLite posibles para ' . $environment . ' y no podemos elegir una sin confirmacion. No se modifico ningun archivo.'); }
        }
        $found = [$source];
        foreach ([$key, $csr, $certificate, $database] as $path) { if (is_file($path)) { $found[] = $path; } }
        return [
            'configured' => true, 'environment' => $environment, 'cuit' => $cuit, 'point_of_sale' => (int) $point,
            'alias' => (string) ($values['ARCA_CERT_ALIAS'] ?? 'arca'), 'profile_path' => $source,
            'key_path' => $key, 'csr_path' => $csr, 'certificate_path' => $certificate, 'database_path' => $database,
            'certificate_obtained' => is_file($certificate), 'certificate_installed' => is_file($certificate),
            'service_authorized' => false, 'connection_validated' => false, 'simulation_completed' => false, 'first_emission_completed' => false,
            'recovered_from_legacy' => true, 'recovered_files' => array_values(array_unique(array_map(fn (string $path): string => $this->relative($path), $found))),
            'updated_at' => date(DATE_ATOM),
        ];
    }
    /** @param array<string,mixed> $candidate */
    private function signature(array $candidate): string
    {
        return implode('|', array_map('strval', [$candidate['cuit'], $candidate['point_of_sale'], $candidate['alias'], $candidate['key_path'], $candidate['certificate_path'], $candidate['database_path']]));
    }
    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function files(string $directory, array $extensions): array
    {
        if (!is_dir($directory)) { return []; }
        $result = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) { if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) { $result[] = $file->getPathname(); } }
        sort($result);
        return $result;
    }
    /** @return list<string> */
    private function artifactPaths(): array
    {
        $result = [];
        foreach ($this->files($this->root . '/storage', ['key', 'csr', 'crt', 'cer', 'pem', 'sqlite', 'sqlite3', 'db']) as $path) { $result[] = $this->relative($path); }
        return $result;
    }
    private function relative(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->root), '/');
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with(strtolower($normalized), strtolower($root . '/')) ? substr($normalized, strlen($root) + 1) : $normalized;
    }
}
