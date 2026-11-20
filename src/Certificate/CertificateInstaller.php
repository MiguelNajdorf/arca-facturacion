<?php
declare(strict_types=1);
namespace ArcaFacturacion\Certificate;
use ArcaFacturacion\Exception\CertificateException;
use ArcaFacturacion\Support\AtomicFile;
final class CertificateInstaller
{
    public const REPLACE_CONFIRMATION = 'REEMPLAZAR_CERTIFICADO_EXISTENTE';
    public function __construct(private readonly CertificateValidator $validator) {}
    public function install(string $source, string $destination, string $privateKeyPath, string $passphrase = '', ?string $overwriteConfirmation = null): CertificateInfo
    {
        if (!is_file($source) || !is_readable($source)) { throw new CertificateException('No se puede leer el certificado indicado.'); }
        if (realpath($source) === realpath($privateKeyPath)) { throw new CertificateException('El certificado y la clave privada no pueden ser el mismo archivo.'); }
        $temporary = $destination . '.validation-' . bin2hex(random_bytes(3));
        AtomicFile::writeNew($temporary, (string) file_get_contents($source), 0644);
        try { $info = $this->validator->validate($temporary, $privateKeyPath, $passphrase); }
        catch (\Throwable $error) { @unlink($temporary); throw $error; }
        $content = (string) file_get_contents($temporary);
        @unlink($temporary);
        if (file_exists($destination)) {
            if ($overwriteConfirmation !== self::REPLACE_CONFIRMATION) { throw new CertificateException('Ya existe un certificado instalado y no sera reemplazado sin confirmacion explicita: ' . $destination); }
            AtomicFile::write($destination, $content, 0644);
        } else { AtomicFile::writeNew($destination, $content, 0644); }
        return $info;
    }
}
