<?php
declare(strict_types=1);

namespace ArcaFacturacion\Diagnostics;

use ArcaFacturacion\Config\ArcaConfig;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use SoapClient;
use Throwable;

/** Diagnostico de transporte y autenticacion. No emite comprobantes ni persiste tickets. */
class OnlineDiagnostics
{
    /** @return list<Check> */
    public function run(ArcaConfig $config): array
    {
        $checks = [];
        $wsaa = $this->prepareEndpoint('WSAA', $config->wsaaUrl(), false, $config, $checks);
        $wsfe = $this->prepareEndpoint('WSFE', $config->wsfeUrl(), true, $config, $checks);
        $cms = null;
        try {
            $cms = $this->signLoginTicketRequest($config);
            $checks[] = new Check('Conectividad', 'Firma local del Login Ticket Request', 'ok', 'la solicitud fue firmada con el certificado y la clave configurados');
        } catch (Throwable $error) {
            $checks[] = new Check('Conectividad', 'Firma local del Login Ticket Request', 'error', 'OpenSSL no pudo firmar la solicitud local: ' . $error->getMessage());
        }
        if ($wsaa === null || $cms === null) {
            $reason = $wsaa === null ? 'requiere un endpoint WSAA accesible' : 'requiere una firma local valida';
            $checks[] = new Check('Conectividad', 'Autenticacion WSAA', 'not_checked', $reason);
            return array_merge($checks, $this->dependentNotChecked('requiere autenticacion WSAA correcta'));
        }
        try {
            $ticketXml = $this->authenticateWsaa($wsaa, $cms);
        } catch (Throwable) {
            $checks[] = new Check('Conectividad', 'Autenticacion WSAA', 'error', 'ARCA no acepto el certificado o no encontro la autorizacion correspondiente. La validacion criptografica local si fue correcta.');
            return array_merge($checks, $this->dependentNotChecked('requiere autenticacion WSAA correcta'));
        }
        try {
            $ticket = $this->parseTicket($ticketXml);
            $checks[] = new Check('Conectividad', 'Autenticacion WSAA', 'ok', 'ARCA autentico el certificado para el servicio WSFE');
            $checks[] = new Check('Conectividad', 'Ticket de Acceso', 'ok', 'WSAA devolvio un Ticket de Acceso valido');
        } catch (Throwable) {
            $checks[] = new Check('Conectividad', 'Autenticacion WSAA', 'error', 'ARCA respondio, pero no fue posible validar el Ticket de Acceso.');
            $checks[] = new Check('Conectividad', 'Ticket de Acceso', 'error', 'la respuesta de WSAA no contiene token y firma validos');
            return array_merge($checks, $this->wsfeNotChecked('requiere un Ticket de Acceso valido'));
        }
        if ($wsfe === null) { return array_merge($checks, $this->wsfeNotChecked('requiere un endpoint WSFE accesible')); }
        try {
            $points = $this->querySalesPoints($wsfe, $ticket, $config);
            $checks[] = new Check('Conectividad', 'Autenticacion WSFE', 'ok', 'WSFE acepto el Ticket de Acceso');
            $checks[] = new Check('Conectividad', 'CUIT representado', 'ok', 'WSFE reconocio el CUIT terminado en ' . substr($config->cuit, -4));
            $recognized = in_array($config->salesPoint, $points, true);
            $checks[] = new Check('Conectividad', 'Punto de venta', $recognized ? 'ok' : 'error', $recognized ? 'ARCA reconocio el punto ' . $config->salesPoint : 'El punto configurado no aparece entre los puntos de venta devueltos por ARCA.');
        } catch (Throwable $error) {
            $checks[] = new Check('Conectividad', 'Autenticacion WSFE', 'error', 'WSFE rechazo el Ticket de Acceso o la autorizacion: ' . $error->getMessage());
            $checks[] = new Check('Conectividad', 'CUIT representado', 'not_checked', 'requiere autenticacion WSFE correcta');
            $checks[] = new Check('Conectividad', 'Punto de venta', 'not_checked', 'requiere autenticacion WSFE y reconocimiento del CUIT');
        }
        return $checks;
    }

    /** @param list<Check> $checks */
    private function prepareEndpoint(string $service, string $url, bool $probeHealth, ArcaConfig $config, array &$checks): ?object
    {
        try {
            $host = $this->resolveDns($url);
            $checks[] = new Check('Conectividad', 'Resolucion DNS ' . $service, 'ok', $host . ' resolvio correctamente');
        } catch (Throwable $error) {
            $checks[] = new Check('Conectividad', 'Resolucion DNS ' . $service, 'connection_error', $error->getMessage());
            $checks[] = new Check('Conectividad', 'Conexion HTTPS ' . $service, 'not_checked', 'requiere resolucion DNS');
            $checks[] = new Check('Conectividad', 'Cliente SOAP ' . $service, 'not_checked', 'requiere conexion HTTPS');
            $checks[] = new Check('Conectividad', 'Endpoint ' . $service, 'not_checked', 'requiere cliente SOAP');
            return null;
        }
        try {
            $this->connectHttps($url, $config->connectionTimeout);
            $checks[] = new Check('Conectividad', 'Conexion HTTPS ' . $service, 'ok', 'TLS verificado');
        } catch (Throwable $error) {
            $checks[] = new Check('Conectividad', 'Conexion HTTPS ' . $service, 'connection_error', $error->getMessage());
            $checks[] = new Check('Conectividad', 'Cliente SOAP ' . $service, 'not_checked', 'requiere conexion HTTPS');
            $checks[] = new Check('Conectividad', 'Endpoint ' . $service, 'not_checked', 'requiere cliente SOAP');
            return null;
        }
        try {
            $client = $this->createSoapClient($url, $config, $probeHealth);
            $checks[] = new Check('Conectividad', 'Cliente SOAP ' . $service, 'ok', 'WSDL cargado y cliente creado');
        } catch (Throwable $error) {
            $checks[] = new Check('Conectividad', 'Cliente SOAP ' . $service, 'connection_error', $error->getMessage());
            $checks[] = new Check('Conectividad', 'Endpoint ' . $service, 'not_checked', 'requiere cliente SOAP');
            return null;
        }
        try {
            $this->probeEndpoint($client, $service, $probeHealth);
            $checks[] = new Check('Conectividad', 'Endpoint ' . $service, 'ok', 'accesible');
            return $client;
        } catch (Throwable $error) {
            $checks[] = new Check('Conectividad', 'Endpoint ' . $service, 'connection_error', $error->getMessage());
            return null;
        }
    }

    protected function resolveDns(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || gethostbyname($host) === $host) { throw new RuntimeException('No fue posible resolver el host de ARCA.'); }
        return $host;
    }
    protected function connectHttps(string $url, int $timeout): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client('ssl://' . $host . ':' . $port, $code, $message, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) { throw new RuntimeException('No fue posible establecer HTTPS con ARCA' . ($message !== '' ? ': ' . $message : '.')); }
        fclose($socket);
    }
    protected function createSoapClient(string $url, ArcaConfig $config, bool $wsfe): object
    {
        return new SoapClient($url, ['soap_version' => SOAP_1_2, 'exceptions' => true, 'trace' => false, 'encoding' => 'UTF-8', 'features' => $wsfe ? SOAP_SINGLE_ELEMENT_ARRAYS : 0, 'cache_wsdl' => WSDL_CACHE_BOTH, 'connection_timeout' => $config->connectionTimeout, 'stream_context' => stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true], 'http' => ['timeout' => $config->requestTimeout, 'user_agent' => 'arca_facturacion/0.1']])]);
    }
    protected function probeEndpoint(object $client, string $service, bool $probeHealth): void
    {
        if ($probeHealth) {
            $response = $client->FEDummy();
            if (!is_object($response)) { throw new RuntimeException('WSFE devolvio una respuesta inesperada.'); }
            return;
        }
        $functions = method_exists($client, '__getFunctions') ? $client->__getFunctions() : [];
        if (!is_array($functions) || !array_filter($functions, static fn (string $function): bool => stripos($function, 'loginCms') !== false)) { throw new RuntimeException('El WSDL de WSAA no expone loginCms.'); }
    }
    protected function signLoginTicketRequest(ArcaConfig $config): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
        $request = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<loginTicketRequest version="1.0"><header><uniqueId>' . time() . '</uniqueId><generationTime>' . $now->modify('-10 minutes')->format('c') . '</generationTime><expirationTime>' . $now->modify('+10 minutes')->format('c') . '</expirationTime></header><service>wsfe</service></loginTicketRequest>';
        $input = tempnam(sys_get_temp_dir(), 'arca-doctor-tra-'); $output = tempnam(sys_get_temp_dir(), 'arca-doctor-cms-');
        if ($input === false || $output === false) { throw new RuntimeException('No se pudieron crear archivos temporales seguros.'); }
        try {
            if (file_put_contents($input, $request, LOCK_EX) === false) { throw new RuntimeException('No se pudo preparar la solicitud temporal.'); }
            $certificate = (string) @file_get_contents($config->certificatePath); $key = (string) @file_get_contents($config->privateKeyPath);
            if (!openssl_pkcs7_sign($input, $output, $certificate, [$key, $config->keyPassphrase], [], PKCS7_DETACHED | PKCS7_BINARY | PKCS7_NOSIGS)) { throw new RuntimeException('OpenSSL rechazo la firma.'); }
            $parts = preg_split('/\r?\n\r?\n/', (string) file_get_contents($output)) ?: [];
            foreach (array_reverse($parts) as $part) {
                $lines = preg_split('/\r?\n/', trim($part)) ?: [];
                $candidate = implode('', array_filter($lines, static fn (string $line): bool => preg_match('/^[A-Za-z0-9+\/=]+$/', trim($line)) === 1));
                if ($candidate !== '' && base64_decode($candidate, true) !== false) { return $candidate; }
            }
            throw new RuntimeException('No se pudo extraer la firma CMS.');
        } finally { @unlink($input); @unlink($output); }
    }
    protected function authenticateWsaa(object $client, string $cms): string
    {
        $response = $client->loginCms(['in0' => $cms]);
        return (string) ($response->loginCmsReturn ?? '');
    }
    /** @return array{token:string,sign:string} */
    protected function parseTicket(string $ticketXml): array
    {
        $xml = $ticketXml !== '' ? @simplexml_load_string($ticketXml) : false;
        if (!$xml || empty($xml->credentials->token) || empty($xml->credentials->sign)) { throw new RuntimeException('Ticket invalido.'); }
        return ['token' => (string) $xml->credentials->token, 'sign' => (string) $xml->credentials->sign];
    }
    /**
     * @param array{token:string,sign:string} $ticket
     * @return list<int>
     */
    protected function querySalesPoints(object $client, array $ticket, ArcaConfig $config): array
    {
        $response = $client->FEParamGetPtosVenta(['Auth' => ['Token' => $ticket['token'], 'Sign' => $ticket['sign'], 'Cuit' => (int) $config->cuit]]);
        $result = $response->FEParamGetPtosVentaResult ?? $response;
        if (!is_object($result)) { throw new RuntimeException('WSFE devolvio una respuesta inesperada.'); }
        $errors = $result->Errors->Err ?? null;
        if ($errors !== null) { $first = is_array($errors) ? reset($errors) : $errors; throw new RuntimeException(is_object($first) ? (string) ($first->Msg ?? 'ARCA rechazo la solicitud.') : 'ARCA rechazo la solicitud.'); }
        $items = $result->ResultGet->PtoVenta ?? []; $items = is_array($items) ? $items : [$items]; $points = [];
        foreach ($items as $item) { if (is_object($item)) { $points[] = (int) ($item->Nro ?? 0); } }
        return $points;
    }
    /** @return list<Check> */
    private function dependentNotChecked(string $reason): array
    {
        return array_merge([new Check('Conectividad', 'Ticket de Acceso', 'not_checked', $reason)], $this->wsfeNotChecked('requiere un Ticket de Acceso valido'));
    }
    /** @return list<Check> */
    private function wsfeNotChecked(string $reason): array
    {
        return [new Check('Conectividad', 'Autenticacion WSFE', 'not_checked', $reason), new Check('Conectividad', 'CUIT representado', 'not_checked', 'requiere autenticacion WSFE correcta'), new Check('Conectividad', 'Punto de venta', 'not_checked', 'requiere autenticacion WSFE y reconocimiento del CUIT')];
    }
}
