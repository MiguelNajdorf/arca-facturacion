<?php
declare(strict_types=1);
namespace ArcaFacturacion\Authentication;
use ArcaFacturacion\Config\ArcaConfig;
use ArcaFacturacion\Exception\AuthenticationException;
use ArcaFacturacion\Support\AtomicFile;
use ArcaFacturacion\Support\Lock;
use DateTimeImmutable;
use DateTimeZone;
use SoapClient;
use SoapFault;
final class WsaaClient
{
    public function __construct(private readonly ArcaConfig $config) {}
    /** @return array{token:string,sign:string} */
    public function credentials(): array
    {
        $cached = $this->readTicket();
        if ($cached !== null) { return $cached; }
        $lock = new Lock($this->config->locksPath(), 'wsaa-' . $this->config->environment . '-' . $this->config->cuit);
        try { return $this->readTicket() ?? $this->renew(); } finally { $lock->release(); }
    }
    /** @return array{token:string,sign:string}|null */
    private function readTicket(): ?array
    {
        $path = $this->ticketPath();
        if (!is_readable($path)) { return null; }
        $xml = @simplexml_load_file($path);
        $expiration = $xml ? strtotime((string) ($xml->header->expirationTime ?? '')) : false;
        if (!$xml || !$expiration || time() + 600 >= $expiration || empty($xml->credentials->token) || empty($xml->credentials->sign)) { return null; }
        return ['token' => (string) $xml->credentials->token, 'sign' => (string) $xml->credentials->sign];
    }
    /** @return array{token:string,sign:string} */
    private function renew(): array
    {
        $cms = $this->sign($this->loginTicketRequest());
        try {
            $client = new SoapClient($this->config->wsaaUrl(), $this->soapOptions());
            $response = $client->loginCms(['in0' => $cms]);
        } catch (SoapFault $error) { throw new AuthenticationException('No fue posible autenticarse con ARCA. Revisa certificado, autorizacion y ambiente.', 'wsaa_soap', $error->getMessage(), $error); }
        $ticketXml = (string) ($response->loginCmsReturn ?? '');
        $xml = $ticketXml !== '' ? @simplexml_load_string($ticketXml) : false;
        if (!$xml || empty($xml->credentials->token) || empty($xml->credentials->sign)) { throw new AuthenticationException('ARCA no devolvio un Ticket de Acceso valido.', 'wsaa_invalid_ticket'); }
        AtomicFile::write($this->ticketPath(), $ticketXml, 0600);
        return ['token' => (string) $xml->credentials->token, 'sign' => (string) $xml->credentials->sign];
    }
    private function loginTicketRequest(): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<loginTicketRequest version="1.0"><header><uniqueId>' . time() . '</uniqueId><generationTime>' . $now->modify('-10 minutes')->format('c') . '</generationTime><expirationTime>' . $now->modify('+10 minutes')->format('c') . '</expirationTime></header><service>wsfe</service></loginTicketRequest>';
    }
    private function sign(string $request): string
    {
        $input = tempnam(sys_get_temp_dir(), 'arca-tra-');
        $output = tempnam(sys_get_temp_dir(), 'arca-cms-');
        if ($input === false || $output === false) { throw new AuthenticationException('No se pudieron crear archivos temporales seguros para firmar.'); }
        try {
            file_put_contents($input, $request, LOCK_EX);
            $certificate = (string) @file_get_contents($this->config->certificatePath);
            $key = (string) @file_get_contents($this->config->privateKeyPath);
            $signed = openssl_pkcs7_sign($input, $output, $certificate, [$key, $this->config->keyPassphrase], [], PKCS7_DETACHED | PKCS7_BINARY | PKCS7_NOSIGS);
            if (!$signed) { throw new AuthenticationException('OpenSSL no pudo firmar la solicitud de acceso.', 'openssl_sign'); }
            $smime = (string) file_get_contents($output);
            $parts = preg_split('/\r?\n\r?\n/', $smime) ?: [];
            foreach (array_reverse($parts) as $part) {
                $lines = preg_split('/\r?\n/', trim($part)) ?: [];
                $candidate = implode('', array_filter($lines, static fn (string $line): bool => preg_match('/^[A-Za-z0-9+\/=]+$/', trim($line)) === 1));
                if ($candidate !== '' && base64_decode($candidate, true) !== false) { return $candidate; }
            }
            throw new AuthenticationException('No se pudo extraer la firma CMS.');
        } finally { @unlink($input); @unlink($output); }
    }
    /** @return array<string,mixed> */
    private function soapOptions(): array
    {
        return ['soap_version' => SOAP_1_2, 'exceptions' => true, 'trace' => false, 'encoding' => 'UTF-8', 'cache_wsdl' => WSDL_CACHE_BOTH, 'connection_timeout' => $this->config->connectionTimeout, 'stream_context' => stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true], 'http' => ['timeout' => $this->config->requestTimeout, 'user_agent' => 'arca_facturacion/0.1']])];
    }
    private function ticketPath(): string
    {
        $directory = $this->config->ticketsPath();
        if (!is_dir($directory)) { @mkdir($directory, 0700, true); }
        return $directory . '/TA-' . $this->config->cuit . '-wsfe-' . $this->config->environment . '.xml';
    }
}
