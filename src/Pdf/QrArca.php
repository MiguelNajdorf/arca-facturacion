<?php
declare(strict_types=1);
namespace ArcaFacturacion\Pdf;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Invoice\Invoice;
use ArcaFacturacion\Invoice\InvoiceResult;
use ArcaFacturacion\Support\Cuit;

/** Texto del QR obligatorio segun la especificacion oficial de ARCA (version 1): https://www.arca.gob.ar/fe/qr/documentos/QRespecificaciones.pdf */
final class QrArca
{
    public const URL = 'https://www.arca.gob.ar/fe/qr/';

    public static function url(Invoice $invoice, InvoiceResult $result, string $cuitEmisor): string
    {
        $missing = !$result->fueAutorizado() || $result->cae() === null || $result->numeroComprobante() === null
            || $result->puntoDeVenta() === null || $result->fecha() === null;
        if ($missing) { throw new ValidationException(['El QR y el PDF solo se generan para comprobantes autorizados con CAE.']); }
        if ($result->tipoComprobante() !== null && $result->tipoComprobante() !== $invoice->voucherType) {
            throw new ValidationException(['La factura no corresponde al resultado: los tipos de comprobante son distintos.']);
        }
        $data = [
            'ver' => 1,
            'fecha' => $result->fecha()->format('Y-m-d'),
            'cuit' => (int) Cuit::normalizar($cuitEmisor),
            'ptoVta' => $result->puntoDeVenta(),
            'tipoCmp' => $invoice->voucherType,
            'nroCmp' => $result->numeroComprobante(),
            'importe' => self::number(round($invoice->total, 2)),
            'moneda' => $invoice->currency,
            'ctz' => self::number(round($invoice->exchangeRate, 6)),
        ];
        // "De corresponder": un consumidor final sin identificar no tiene documento que informar.
        if (!($invoice->documentType === 99 && $invoice->documentNumber === '0')) {
            $data['tipoDocRec'] = $invoice->documentType;
            // shortcut: entero de 64 bits (hasta 18 digitos); alcanza para CUIT, CUIL y DNI.
            $data['nroDocRec'] = (int) $invoice->documentNumber;
        }
        $data['tipoCodAut'] = 'E';
        $data['codAut'] = (int) $result->cae();
        return self::codificar($data);
    }

    /** @param array<string,int|float|string> $data */
    public static function codificar(array $data): string
    {
        return self::URL . '?p=' . base64_encode(json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** 12100.0 se informa como 12100, igual que el ejemplo oficial. */
    private static function number(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : $value;
    }
}
