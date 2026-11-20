<?php
declare(strict_types=1);
namespace ArcaFacturacion\Infrastructure;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Invoice\Invoice;
final class RequestMapper
{
    private const VAT_IDS = ['0' => 3, '10.5' => 4, '21' => 5, '27' => 6, '5' => 8, '2.5' => 9];
    /** @return array<string,mixed> */
    public function map(Invoice $invoice, int $salesPoint, int $number): array
    {
        $detail = [
            'Concepto' => $invoice->concept, 'DocTipo' => $invoice->documentType, 'DocNro' => $invoice->documentNumber,
            'CbteDesde' => $number, 'CbteHasta' => $number, 'CbteFch' => (int) ($invoice->date ?? new \DateTimeImmutable('today'))->format('Ymd'),
            'ImpTotal' => round($invoice->total, 2), 'ImpTotConc' => round($invoice->untaxed, 2), 'ImpNeto' => round($invoice->net, 2),
            'ImpOpEx' => round($invoice->exempt, 2), 'ImpIVA' => round($invoice->vat, 2), 'ImpTrib' => round($invoice->taxes, 2),
            'MonId' => $invoice->currency, 'MonCotiz' => round($invoice->exchangeRate, 6), 'CondicionIVAReceptorId' => $invoice->receiverVatCondition,
        ];
        if ($invoice->concept !== 1) {
            $detail['FchServDesde'] = (int) $invoice->serviceFrom?->format('Ymd');
            $detail['FchServHasta'] = (int) $invoice->serviceTo?->format('Ymd');
            $detail['FchVtoPago'] = (int) $invoice->paymentDue?->format('Ymd');
        }
        if ($invoice->vatBreakdown !== []) {
            $lines = [];
            foreach ($invoice->vatBreakdown as $line) {
                $key = rtrim(rtrim(number_format((float) $line['rate'], 2, '.', ''), '0'), '.');
                if (!isset(self::VAT_IDS[$key])) { throw new ValidationException(["La alicuota de IVA {$key}% no tiene un codigo WSFE soportado."]); }
                $lines[] = ['Id' => self::VAT_IDS[$key], 'BaseImp' => round((float) $line['base'], 2), 'Importe' => round((float) $line['amount'], 2)];
            }
            $detail['Iva'] = ['AlicIva' => $lines];
        }
        if ($invoice->associated !== []) {
            $items = [];
            foreach ($invoice->associated as $associated) {
                $item = ['Tipo' => (int) ($associated['type'] ?? 0), 'PtoVta' => (int) ($associated['sales_point'] ?? 0), 'Nro' => (int) ($associated['number'] ?? 0)];
                if (!empty($associated['cuit'])) { $item['Cuit'] = (string) $associated['cuit']; }
                if (!empty($associated['date'])) { $item['CbteFch'] = (int) str_replace('-', '', (string) $associated['date']); }
                $items[] = $item;
            }
            $detail['CbtesAsoc'] = ['CbteAsoc' => $items];
        }
        return ['FeCAEReq' => ['FeCabReq' => ['CantReg' => 1, 'PtoVta' => $salesPoint, 'CbteTipo' => $invoice->voucherType], 'FeDetReq' => ['FECAEDetRequest' => $detail]]];
    }
}
