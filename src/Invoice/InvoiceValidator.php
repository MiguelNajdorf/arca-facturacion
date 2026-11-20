<?php
declare(strict_types=1);
namespace ArcaFacturacion\Invoice;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Support\Cuit;
final class InvoiceValidator
{
    public function validate(Invoice $invoice): void
    {
        $errors = [];
        if ($invoice->total <= 0) { $errors[] = 'El importe total debe ser mayor a cero.'; }
        foreach ([$invoice->net, $invoice->untaxed, $invoice->exempt, $invoice->vat, $invoice->taxes] as $amount) { if ($amount < 0) { $errors[] = 'Los importes parciales no pueden ser negativos.'; break; } }
        $sum = round($invoice->net + $invoice->untaxed + $invoice->exempt + $invoice->vat + $invoice->taxes, 2);
        if (abs(round($invoice->total, 2) - $sum) > 0.01) { $errors[] = sprintf('El total $%.2f no coincide con la suma informada $%.2f (diferencia $%.2f).', $invoice->total, $sum, abs($invoice->total - $sum)); }
        if ($invoice->documentType === 80 && !Cuit::esValido($invoice->documentNumber)) { $errors[] = 'El CUIT del receptor no es valido.'; }
        if ($invoice->documentType === 99 && $invoice->documentNumber !== '0') { $errors[] = 'Consumidor final sin identificar debe usar documento 0.'; }
        if ($invoice->receiverVatCondition < 1) { $errors[] = 'Falta la condicion frente al IVA del receptor.'; }
        if (!in_array($invoice->voucherType, [1, 3, 6, 8, 11, 13], true)) { $errors[] = 'El tipo de comprobante no esta soportado por esta version.'; }
        $classC = in_array($invoice->voucherType, [11, 12, 13, 15], true);
        if ($classC && ($invoice->vat !== 0.0 || $invoice->vatBreakdown !== [])) { $errors[] = 'Los comprobantes clase C no discriminan IVA.'; }
        if (!$classC && $invoice->vat > 0 && $invoice->vatBreakdown === []) { $errors[] = 'El IVA informado requiere el detalle de alicuotas.'; }
        $vatSum = array_sum(array_map(static fn (array $line): float => (float) $line['amount'], $invoice->vatBreakdown));
        if (abs($vatSum - $invoice->vat) > 0.01) { $errors[] = 'El IVA total no coincide con el detalle de alicuotas.'; }
        if ($invoice->concept !== 1 && (!$invoice->serviceFrom || !$invoice->serviceTo || !$invoice->paymentDue)) { $errors[] = 'Los servicios requieren periodo y fecha de vencimiento de pago.'; }
        if ($invoice->serviceFrom && $invoice->serviceTo && $invoice->serviceFrom > $invoice->serviceTo) { $errors[] = 'La fecha inicial del servicio no puede ser posterior a la fecha final.'; }
        if (in_array($invoice->voucherType, [3, 8, 13], true) && $invoice->associated === []) { $errors[] = 'La nota de credito requiere al menos un comprobante asociado.'; }
        foreach ($invoice->associated as $associated) {
            if ((int) ($associated['type'] ?? 0) < 1 || (int) ($associated['sales_point'] ?? 0) < 1 || (int) ($associated['number'] ?? 0) < 1) { $errors[] = 'Cada comprobante asociado requiere tipo, punto de venta y numero positivos.'; break; }
            if (!empty($associated['cuit']) && !Cuit::esValido((string) $associated['cuit'])) { $errors[] = 'El CUIT de un comprobante asociado no es valido.'; break; }
        }
        if (!preg_match('/^[A-Z]{3}$/', $invoice->currency) || $invoice->exchangeRate <= 0) { $errors[] = 'La moneda o su cotizacion no son validas.'; }
        if ($invoice->currency === 'PES' && abs($invoice->exchangeRate - 1.0) > 0.000001) { $errors[] = 'La cotizacion para pesos debe ser 1.'; }
        if ($errors !== []) { throw new ValidationException(array_values(array_unique($errors))); }
    }
}
