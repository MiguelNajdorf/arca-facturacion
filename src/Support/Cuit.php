<?php
declare(strict_types=1);
namespace ArcaFacturacion\Support;
final class Cuit
{
    public static function normalizar(string $value): string { return preg_replace('/\D/', '', $value) ?? ''; }
    public static function esValido(string $value): bool
    {
        $value = self::normalizar($value);
        if (!preg_match('/^\d{11}$/', $value)) { return false; }
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $index => $weight) { $sum += (int) $value[$index] * $weight; }
        $digit = 11 - ($sum % 11);
        $digit = $digit === 11 ? 0 : ($digit === 10 ? 9 : $digit);
        return $digit === (int) $value[10];
    }
}
