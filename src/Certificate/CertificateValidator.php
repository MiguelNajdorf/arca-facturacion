<?php
declare(strict_types=1);
namespace ArcaFacturacion\Certificate;
use ArcaFacturacion\Exception\CertificateException;
use DateTimeImmutable;
use DateTimeZone;
final class CertificateValidator
{
    public function validate(string $certificatePath, string $privateKeyPath, string $passphrase = ''): CertificateInfo
    {
        if (!is_file($certificatePath) || !is_readable($certificatePath)) { throw new CertificateException('No se encontro un certificado legible en la ruta configurada.'); }
        if (!is_file($privateKeyPath) || !is_readable($privateKeyPath)) { throw new CertificateException('No se encontro una clave privada legible en la ruta configurada.'); }
        $certificateContent = (string) file_get_contents($certificatePath);
        $keyContent = (string) file_get_contents($privateKeyPath);
        if (str_contains($certificateContent, 'CERTIFICATE REQUEST')) { throw new CertificateException('El archivo indicado es un CSR, no el certificado emitido por ARCA.'); }
        if (str_contains($certificateContent, 'PRIVATE KEY')) { throw new CertificateException('Se indico una clave privada donde se esperaba el certificado.'); }
        $certificate = @openssl_x509_read($certificateContent);
        if ($certificate === false) { throw new CertificateException('El archivo no contiene un certificado X.509 valido.'); }
        $privateKey = @openssl_pkey_get_private($keyContent, $passphrase);
        if ($privateKey === false) { throw new CertificateException('La clave privada no es valida o su frase secreta es incorrecta.'); }
        $matches = openssl_x509_check_private_key($certificate, $privateKey);
        if (!$matches) { throw new CertificateException('El certificado no corresponde a la clave privada instalada. No reemplaces archivos al azar: instala el par correcto o genera una nueva solicitud.'); }
        $parsed = openssl_x509_parse($certificate);
        if (!is_array($parsed) || !isset($parsed['validFrom_time_t'], $parsed['validTo_time_t'])) { throw new CertificateException('No se pudieron leer las fechas del certificado.'); }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $from = (new DateTimeImmutable('@' . (int) $parsed['validFrom_time_t']))->setTimezone(new DateTimeZone('UTC'));
        $until = (new DateTimeImmutable('@' . (int) $parsed['validTo_time_t']))->setTimezone(new DateTimeZone('UTC'));
        if ($from > $now->modify('+5 minutes')) { throw new CertificateException('El certificado todavia no esta vigente.'); }
        if ($until <= $now) { throw new CertificateException('El certificado esta vencido.'); }
        $days = (int) $now->diff($until)->format('%a');
        return new CertificateInfo($this->dn($parsed['subject'] ?? []), $this->dn($parsed['issuer'] ?? []), $from, $until, $days, true, $days <= 45);
    }
    /** @param mixed $value */
    private function dn(mixed $value): string
    {
        if (!is_array($value)) { return 'No disponible'; }
        $parts = [];
        foreach ($value as $key => $item) { $parts[] = $key . '=' . (is_array($item) ? implode(',', $item) : (string) $item); }
        return implode(', ', $parts);
    }
}
