<?php
declare(strict_types=1);
namespace ArcaFacturacion\Infrastructure;
use ArcaFacturacion\Authentication\WsaaClient;
use ArcaFacturacion\Config\ArcaConfig;
use ArcaFacturacion\Exception\ConnectivityException;
use ArcaFacturacion\Exception\ArcaResponseException;
use ArcaFacturacion\Invoice\Invoice;
use SoapClient;
use SoapFault;
final class SoapArcaGateway implements ArcaGateway
{
    private ?SoapClient $client = null;
    public function __construct(private readonly ArcaConfig $config, private readonly WsaaClient $wsaa, private readonly RequestMapper $mapper) {}
    public function health(): array
    {
        $result = $this->execute('FEDummy', [], false);
        return ['app_server' => (string) ($result->AppServer ?? ''), 'db_server' => (string) ($result->DbServer ?? ''), 'auth_server' => (string) ($result->AuthServer ?? '')];
    }
    public function lastVoucher(int $salesPoint, int $voucherType): int
    {
        $result = $this->execute('FECompUltimoAutorizado', ['PtoVta' => $salesPoint, 'CbteTipo' => $voucherType]);
        return (int) ($result->CbteNro ?? 0);
    }
    public function authorize(Invoice $invoice, int $salesPoint, int $voucherNumber): array
    {
        $result = $this->execute('FECAESolicitar', $this->mapper->map($invoice, $salesPoint, $voucherNumber));
        $response = $result->FeDetResp->FECAEDetResponse ?? null;
        if (is_array($response)) { $response = reset($response); }
        if (!is_object($response)) { throw new ArcaResponseException('ARCA no devolvio el detalle del comprobante.', 'wsfe_missing_detail'); }
        $errors = array_merge($this->messages($result->Errors->Err ?? null), $this->messages($response->Errors->Err ?? null));
        $observations = array_map(static fn (array $item): string => trim(($item['code'] ? '(' . $item['code'] . ') ' : '') . $item['message']), $this->messages($response->Observaciones->Obs ?? null));
        if ((string) ($response->Resultado ?? '') !== 'A') {
            if ($errors === []) { $errors[] = ['code' => null, 'message' => $observations !== [] ? implode(' | ', $observations) : 'ARCA rechazo el comprobante.']; }
            return ['status' => 'rejected', 'errors' => $errors, 'observations' => $observations];
        }
        $cae = (string) ($response->CAE ?? '');
        $expiration = $this->formatDate((string) ($response->CAEFchVto ?? ''));
        if ($cae === '' || $expiration === '') { throw new ArcaResponseException('ARCA aprobo el comprobante sin un CAE completo.', 'wsfe_incomplete_approval'); }
        return ['status' => 'authorized', 'cae' => $cae, 'cae_expiration' => $expiration, 'voucher_number' => (int) ($response->CbteHasta ?? $voucherNumber), 'sales_point' => $salesPoint, 'voucher_type' => $invoice->voucherType, 'date' => ($invoice->date ?? new \DateTimeImmutable('today'))->format('Y-m-d'), 'observations' => $observations];
    }
    public function findVoucher(int $salesPoint, int $voucherType, int $voucherNumber): ?array
    {
        try {
            $result = $this->execute('FECompConsultar', ['FeCompConsReq' => ['CbteTipo' => $voucherType, 'CbteNro' => $voucherNumber, 'PtoVta' => $salesPoint]]);
        } catch (ArcaResponseException $error) {
            if (in_array($error->codigo(), ['602', '10016'], true)) { return null; }
            throw $error;
        }
        $data = $result->ResultGet ?? null;
        if (!is_object($data) || empty($data->CodAutorizacion)) { return null; }
        return ['status' => 'authorized', 'cae' => (string) $data->CodAutorizacion, 'cae_expiration' => $this->formatDate((string) ($data->FchVto ?? '')), 'voucher_number' => $voucherNumber, 'sales_point' => $salesPoint, 'voucher_type' => $voucherType, 'date' => $this->formatDate((string) ($data->CbteFch ?? '')), 'observations' => []];
    }
    public function salesPoints(): array
    {
        $result = $this->execute('FEParamGetPtosVenta', []);
        $items = $result->ResultGet->PtoVenta ?? [];
        $items = is_array($items) ? $items : [$items];
        $response = [];
        foreach ($items as $item) { if (is_object($item)) { $response[] = ['number' => (int) ($item->Nro ?? 0), 'type' => (string) ($item->EmisionTipo ?? ''), 'blocked' => (string) ($item->Bloqueado ?? ''), 'from' => $this->formatDate((string) ($item->FchBaja ?? ''))]; } }
        return $response;
    }
    public function receiverVatConditions(string $voucherClass = ''): array
    {
        $voucherClass = strtoupper(trim($voucherClass));
        if ($voucherClass !== '' && !in_array($voucherClass, ['A', 'B', 'C', 'M', 'BU'], true)) { throw new ArcaResponseException('La clase debe ser A, B, C, M o BU.'); }
        $result = $this->execute('FEParamGetCondicionIvaReceptor', $voucherClass === '' ? [] : ['ClaseCmp' => $voucherClass]);
        $items = $result->ResultGet->CondicionIvaReceptor ?? [];
        $items = is_array($items) ? $items : [$items];
        $response = [];
        foreach ($items as $item) { if (is_object($item)) { $response[] = ['id' => (int) ($item->Id ?? 0), 'description' => (string) ($item->Desc ?? ''), 'voucher_class' => (string) ($item->Cmp_Clase ?? '')]; } }
        return $response;
    }
    public function voucherTypes(): array
    {
        $result = $this->execute('FEParamGetTiposCbte', []);
        $items = $result->ResultGet->CbteTipo ?? [];
        $items = is_array($items) ? $items : [$items];
        $response = [];
        foreach ($items as $item) { if (is_object($item)) { $response[] = ['id' => (int) ($item->Id ?? 0), 'description' => (string) ($item->Desc ?? ''), 'valid_from' => $this->formatDate((string) ($item->FchDesde ?? '')), 'valid_until' => $this->formatDate((string) ($item->FchHasta ?? ''))]; } }
        return $response;
    }
    /** @param array<string, mixed> $parameters */
    private function execute(string $operation, array $parameters, bool $authenticated = true): object
    {
        if ($authenticated) {
            $ticket = $this->wsaa->credentials();
            $parameters = ['Auth' => ['Token' => $ticket['token'], 'Sign' => $ticket['sign'], 'Cuit' => (int) $this->config->cuit]] + $parameters;
        }
        try { $response = $this->soap()->{$operation}($parameters); }
        catch (SoapFault $error) { throw new ConnectivityException('No fue posible comunicarse con ARCA. La operacion quedo registrada para poder reconciliarla.', 'soap_' . $operation, $error->getMessage(), $error); }
        $resultName = $operation . 'Result';
        $result = $response->{$resultName} ?? $response;
        if (!is_object($result)) { throw new ArcaResponseException('ARCA devolvio una respuesta inesperada.', 'invalid_' . $operation); }
        $errors = $this->messages($result->Errors->Err ?? null);
        if ($errors !== []) {
            $first = $errors[0];
            throw new ArcaResponseException($first['message'], $first['code'], json_encode($errors, JSON_UNESCAPED_UNICODE));
        }
        return $result;
    }
    private function soap(): SoapClient
    {
        if ($this->client === null) {
            try { $this->client = new SoapClient($this->config->wsfeUrl(), ['soap_version' => SOAP_1_2, 'exceptions' => true, 'trace' => false, 'encoding' => 'UTF-8', 'features' => SOAP_SINGLE_ELEMENT_ARRAYS, 'cache_wsdl' => WSDL_CACHE_BOTH, 'connection_timeout' => $this->config->connectionTimeout, 'stream_context' => stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true], 'http' => ['timeout' => $this->config->requestTimeout, 'user_agent' => 'arca_facturacion/0.1']])]); }
            catch (SoapFault $error) { throw new ConnectivityException('No se pudo iniciar el cliente WSFE.', 'wsfe_client', $error->getMessage(), $error); }
        }
        return $this->client;
    }
    /** @return list<array{code:?string,message:string}> */
    private function messages(mixed $items): array
    {
        if ($items === null) { return []; }
        $items = is_array($items) ? $items : [$items];
        $messages = [];
        foreach ($items as $item) { if (is_object($item)) { $messages[] = ['code' => isset($item->Code) ? (string) $item->Code : null, 'message' => trim((string) ($item->Msg ?? 'Error sin detalle'))]; } }
        return $messages;
    }
    private function formatDate(string $date): string
    {
        $digits = preg_replace('/\D/', '', $date) ?? '';
        return strlen($digits) === 8 ? substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2) : $date;
    }
}
