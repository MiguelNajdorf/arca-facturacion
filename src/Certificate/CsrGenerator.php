<?php
declare(strict_types=1);
namespace ArcaFacturacion\Certificate;
use ArcaFacturacion\Exception\CertificateException;
use ArcaFacturacion\Support\AtomicFile;
use ArcaFacturacion\Support\Cuit;
final class CsrGenerator
{
    public const REPLACE_CONFIRMATION = 'REGENERAR_CREDENCIALES_EXISTENTES';
    public function __construct(private readonly ?OpenSslConfiguration $openSslConfiguration = null) {}
    /** @return array{key:string,csr:string} */
    public function generate(string $directory, string $cuit, string $alias, ?string $overwriteConfirmation = null): array
    {
        $cuit = Cuit::normalizar($cuit);
        if (!Cuit::esValido($cuit)) { throw new CertificateException('El CUIT para el CSR no es valido.'); }
        $alias = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $alias) ?? '', '-');
        if ($alias === '') { throw new CertificateException('El alias debe contener letras o numeros.'); }
        $keyPath = rtrim($directory, '/\\') . '/privada.key';
        $csrPath = rtrim($directory, '/\\') . '/solicitud.csr';
        $existing = array_values(array_filter([$keyPath, $csrPath], 'is_file'));
        if ($existing !== [] && $overwriteConfirmation !== self::REPLACE_CONFIRMATION) { throw new CertificateException('No se generaron credenciales porque ya existen estos archivos: ' . implode(', ', $existing) . '. Para reemplazarlos se requiere una confirmacion explicita y visible.'); }
        $options = ($this->openSslConfiguration ?? new OpenSslConfiguration())->options([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ]);
        $key = openssl_pkey_new($options);
        if ($key === false) { throw new CertificateException('OpenSSL no pudo crear la clave privada con la configuracion del PHP activo. Revisa que openssl.cnf exista junto a la instalacion de PHP.'); }
        $csr = openssl_csr_new(['countryName' => 'AR', 'organizationName' => $alias, 'commonName' => $alias, 'serialNumber' => 'CUIT ' . $cuit], $key, $options);
        if ($csr === false || !openssl_pkey_export($key, $keyPem, null, $options) || !openssl_csr_export($csr, $csrPem)) { throw new CertificateException('OpenSSL no pudo exportar la clave y el CSR.'); }
        if ($existing === []) {
            AtomicFile::writeNew($keyPath, $keyPem, 0600);
            AtomicFile::writeNew($csrPath, $csrPem, 0644);
        } else {
            AtomicFile::write($keyPath, $keyPem, 0600);
            AtomicFile::write($csrPath, $csrPem, 0644);
        }
        return ['key' => $keyPath, 'csr' => $csrPath];
    }
}
