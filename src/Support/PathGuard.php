<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
use ArcaFacturacion\Exception\ConfigurationException;
final class PathGuard
{
    public static function absolute(string $path, string $root): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, "\0")) { throw new ConfigurationException('La ruta configurada no es valida.'); }
        if (preg_match('#^[A-Za-z]:/#', $path) || str_starts_with($path, '/')) { $absolute = $path; }
        else {
            $segments = [];
            foreach (explode('/', $path) as $segment) {
                if ($segment === '' || $segment === '.') { continue; }
                if ($segment === '..') {
                    if ($segments === []) { throw new ConfigurationException('La ruta intenta salir del proyecto.'); }
                    array_pop($segments);
                } else { $segments[] = $segment; }
            }
            $absolute = rtrim(str_replace('\\', '/', $root), '/') . '/' . implode('/', $segments);
        }
        $public = strtolower(rtrim(str_replace('\\', '/', realpath($root . '/public') ?: $root . '/public'), '/'));
        $candidate = strtolower(rtrim($absolute, '/'));
        if ($candidate === $public || str_starts_with($candidate, $public . '/')) {
            throw new ConfigurationException('Los archivos sensibles no pueden ubicarse dentro de public/.');
        }
        return $absolute;
    }
}
