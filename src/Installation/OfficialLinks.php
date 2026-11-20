<?php
declare(strict_types=1);
namespace ArcaFacturacion\Installation;
final class OfficialLinks
{
    /** @return array<string,string> */
    public function all(): array
    {
        return [
            'certificates' => $this->value('ARCA_DOC_CERTIFICATES_URL', 'https://www.arca.gob.ar/ws/documentacion/certificados.asp'),
            'wsass' => $this->value('ARCA_DOC_WSASS_URL', 'https://www.arca.gob.ar/ws/WSASS/html/index.html'),
            'wsass_certificate' => $this->value('ARCA_DOC_WSASS_CERTIFICATE_URL', 'https://www.arca.gob.ar/ws/WSASS/html/crearcertificado.html'),
            'wsass_authorization' => $this->value('ARCA_DOC_WSASS_AUTHORIZATION_URL', 'https://www.arca.gob.ar/ws/WSASS/html/crearautorizacion.html'),
            'production_certificate' => $this->value('ARCA_DOC_PRODUCTION_CERTIFICATE_URL', 'https://www.arca.gob.ar/ws/WSAA/WSAA.ObtenerCertificado.pdf'),
            'production_authorization' => $this->value('ARCA_DOC_PRODUCTION_AUTHORIZATION_URL', 'https://www.arca.gob.ar/ws/WSAA/ADMINREL.DelegarWS.pdf'),
            'billing' => $this->value('ARCA_DOC_BILLING_URL', 'https://www.arca.gob.ar/fe/'),
        ];
    }
    private function value(string $key, string $default): string
    {
        $value = getenv($key);
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) ? $value : $default;
    }
}
