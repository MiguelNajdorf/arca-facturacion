<?php
declare(strict_types=1);
namespace ArcaFacturacion\Pdf;
use ArcaFacturacion\Exception\ConfigurationException;
use ArcaFacturacion\Exception\ValidationException;
use ArcaFacturacion\Invoice\Invoice;
use ArcaFacturacion\Invoice\InvoiceResult;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use DateTimeImmutable;
use FPDF;

/**
 * Representacion impresa (A4) de un comprobante autorizado, con el QR obligatorio de ARCA.
 *
 * $datos:
 * - emisor: razon_social (obligatorio), domicilio, condicion_iva, ingresos_brutos, inicio_actividades (AAAA-MM-DD)
 * - receptor: nombre, domicilio, condicion_iva (si falta se usa la condicion informada en la factura)
 * - items: lista de [descripcion, cantidad (1 por defecto), precio]. En clase A el precio es neto; en B y C, final.
 * - observaciones: texto libre
 */
final class FacturaPdf
{
    private const TYPES = [1 => ['A', 'FACTURA'], 3 => ['A', 'NOTA DE CREDITO'], 6 => ['B', 'FACTURA'], 8 => ['B', 'NOTA DE CREDITO'], 11 => ['C', 'FACTURA'], 13 => ['C', 'NOTA DE CREDITO']];
    private const DOCUMENTS = [80 => 'CUIT', 86 => 'CUIL', 94 => 'Pasaporte', 96 => 'DNI', 99 => 'Documento'];
    private const VAT_CONDITIONS = [1 => 'IVA Responsable Inscripto', 4 => 'IVA Sujeto Exento', 5 => 'Consumidor Final', 6 => 'Responsable Monotributo', 7 => 'Sujeto No Categorizado', 8 => 'Proveedor del Exterior', 9 => 'Cliente del Exterior', 10 => 'IVA Liberado - Ley N° 19.640', 13 => 'Monotributista Social', 15 => 'IVA No Alcanzado', 16 => 'Monotributo Trabajador Independiente Promovido'];
    private const LEFT = 10.0;
    private const WIDTH = 190.0;

    private FPDF $pdf;

    public function __construct(private readonly string $cuitEmisor) {}

    /** @param array<string,mixed> $datos */
    public function generar(Invoice $invoice, InvoiceResult $result, array $datos): string
    {
        if (!class_exists(FPDF::class) || !class_exists(QRCode::class)) {
            throw new ConfigurationException('Para generar el PDF faltan dependencias. Ejecuta: composer install');
        }
        $qr = QrArca::url($invoice, $result, $this->cuitEmisor);
        $type = self::TYPES[$invoice->voucherType] ?? throw new ValidationException(['El tipo de comprobante no tiene representacion PDF en esta version.']);
        $emisor = $this->section($datos, 'emisor');
        if (trim($emisor['razon_social'] ?? '') === '') { throw new ValidationException(['Falta emisor.razon_social para generar el PDF.']); }
        $receptor = $this->section($datos, 'receptor');
        $items = $this->items($datos, $invoice, $type[0]);

        $this->pdf = new FPDF('P', 'mm', 'A4');
        // shortcut: el pie con QR y CAE va solo en la ultima pagina; repetirlo por pagina requiere un Footer() propio.
        $this->pdf->SetAutoPageBreak(true, 50);
        $this->pdf->SetCreator('arca-facturacion');
        $this->pdf->SetTitle($this->text(sprintf('%s %s %05d-%08d', $type[1], $type[0], (int) $result->puntoDeVenta(), (int) $result->numeroComprobante())));
        $this->pdf->AddPage();
        $this->header($invoice, $result, $type, $emisor);
        $this->receiver($invoice, $receptor);
        $this->itemsTable($items);
        $this->totals($invoice, $type[0]);
        if (trim((string) ($datos['observaciones'] ?? '')) !== '') {
            $this->pdf->Ln(4);
            $this->pdf->SetFont('Helvetica', 'B', 9);
            $this->pdf->Cell(0, 5, 'Observaciones', 0, 1);
            $this->pdf->SetFont('Helvetica', '', 9);
            $this->pdf->MultiCell(0, 4.5, $this->text((string) $datos['observaciones']));
        }
        $this->footer($result, $qr);
        return $this->pdf->Output('S');
    }

    /**
     * @param array{0:string,1:string} $type
     * @param array<string,string> $emisor
     */
    private function header(Invoice $invoice, InvoiceResult $result, array $type, array $emisor): void
    {
        $p = $this->pdf;
        $top = 10.0;
        $p->Rect(self::LEFT, $top, self::WIDTH, 52);
        $p->Line(105, $top + 16, 105, $top + 52);
        $p->SetFillColor(255, 255, 255);
        $p->Rect(97, $top, 16, 16, 'DF');
        $p->SetFont('Helvetica', 'B', 26);
        $p->SetXY(97, $top + 1);
        $p->Cell(16, 10, $type[0], 0, 0, 'C');
        $p->SetFont('Helvetica', 'B', 7);
        $p->SetXY(97, $top + 11);
        $p->Cell(16, 4, sprintf('COD. %03d', $invoice->voucherType), 0, 0, 'C');

        $p->SetXY(13, $top + 4);
        $p->SetFont('Helvetica', 'B', 14);
        $p->MultiCell(82, 6, $this->text($emisor['razon_social']));
        $p->SetXY(13, $top + 22);
        $this->labelLines(82, [
            ['Razón social', $emisor['razon_social']],
            ['Domicilio comercial', $emisor['domicilio'] ?? ''],
            ['Condición frente al IVA', $emisor['condicion_iva'] ?? ''],
        ]);

        $p->SetXY(117, $top + 4);
        $p->SetFont('Helvetica', 'B', 16);
        $p->Cell(80, 7, $this->text($type[1]), 0, 2);
        $p->SetFont('Helvetica', 'B', 10);
        $p->Cell(80, 6, sprintf('Punto de venta: %05d   Comp. Nro: %08d', (int) $result->puntoDeVenta(), (int) $result->numeroComprobante()), 0, 2);
        $p->SetXY(108, $top + 22);
        $this->labelLines(88, [
            ['Fecha de emisión', $this->date($result->fecha())],
            ['CUIT', $this->cuitEmisor],
            ['Ingresos Brutos', $emisor['ingresos_brutos'] ?? ''],
            ['Inicio de actividades', $this->date(isset($emisor['inicio_actividades']) ? new DateTimeImmutable($emisor['inicio_actividades']) : null)],
        ]);
        $p->SetY($top + 54);
        if ($invoice->concept !== 1) {
            $p->SetX(self::LEFT);
            $p->SetFont('Helvetica', '', 9);
            $p->Cell(self::WIDTH, 7, $this->text(sprintf('Período facturado desde: %s   hasta: %s   Fecha de vto. para el pago: %s', $this->date($invoice->serviceFrom), $this->date($invoice->serviceTo), $this->date($invoice->paymentDue))), 1, 1);
            $p->Ln(2);
        }
    }

    /** @param array<string,string> $receptor */
    private function receiver(Invoice $invoice, array $receptor): void
    {
        $p = $this->pdf;
        $anonymous = $invoice->documentType === 99 && $invoice->documentNumber === '0';
        $document = $anonymous ? 'Consumidor final' : (self::DOCUMENTS[$invoice->documentType] ?? 'Doc. ' . $invoice->documentType) . ': ' . $invoice->documentNumber;
        $condition = $receptor['condicion_iva'] ?? (self::VAT_CONDITIONS[$invoice->receiverVatCondition] ?? 'Código ' . $invoice->receiverVatCondition);
        $y = $p->GetY();
        $p->SetXY(13, $y + 2);
        $lines = [
            ['Receptor', $document],
            ['Apellido y nombre / Razón social', $receptor['nombre'] ?? ''],
            ['Condición frente al IVA', $condition],
            ['Domicilio', $receptor['domicilio'] ?? ''],
        ];
        if ($invoice->currency !== 'PES') {
            $lines[] = ['Moneda', sprintf('%s - Cotización: %s', $invoice->currency, rtrim(rtrim(number_format($invoice->exchangeRate, 6, ',', '.'), '0'), ','))];
        }
        $this->labelLines(180, $lines);
        $p->Rect(self::LEFT, $y, self::WIDTH, $p->GetY() - $y + 2);
        $p->SetY($p->GetY() + 5);
    }

    /** @param list<array{descripcion:string,cantidad:float,precio:float}> $items */
    private function itemsTable(array $items): void
    {
        if ($items === []) { return; }
        $p = $this->pdf;
        $widths = [100.0, 25.0, 32.5, 32.5];
        $p->SetFillColor(225, 225, 225);
        $p->SetFont('Helvetica', 'B', 9);
        $p->SetX(self::LEFT);
        foreach (['Descripción', 'Cantidad', 'Precio unit.', 'Subtotal'] as $i => $title) { $p->Cell($widths[$i], 7, $this->text($title), 1, 0, $i === 0 ? 'L' : 'R', true); }
        $p->Ln();
        $p->SetFont('Helvetica', '', 9);
        foreach ($items as $item) {
            if ($p->GetY() > 240) { $p->AddPage(); }
            $y = $p->GetY();
            $p->SetXY(self::LEFT, $y);
            $p->MultiCell($widths[0], 5, $this->text($item['descripcion']), 'LR', 'L');
            $height = $p->GetY() - $y;
            $p->SetXY(self::LEFT + $widths[0], $y);
            $p->Cell($widths[1], $height, $this->quantity($item['cantidad']), 'R', 0, 'R');
            $p->Cell($widths[2], $height, $this->money($item['precio']), 'R', 0, 'R');
            $p->Cell($widths[3], $height, $this->money($item['cantidad'] * $item['precio']), 'R', 1, 'R');
        }
        $p->Line(self::LEFT, $p->GetY(), self::LEFT + self::WIDTH, $p->GetY());
        $p->Ln(4);
    }

    private function totals(Invoice $invoice, string $letter): void
    {
        $lines = [];
        if ($letter === 'A') {
            $lines[] = ['Importe neto gravado', $invoice->net];
            foreach ($invoice->vatBreakdown as $vat) { $lines[] = ['IVA ' . $this->rate((float) $vat['rate']) . '%', (float) $vat['amount']]; }
            if ($invoice->untaxed > 0) { $lines[] = ['Importe no gravado', $invoice->untaxed]; }
            if ($invoice->exempt > 0) { $lines[] = ['Importe exento', $invoice->exempt]; }
        } elseif ($invoice->taxes > 0) {
            $lines[] = ['Subtotal', $invoice->total - $invoice->taxes];
        }
        if ($invoice->taxes > 0) { $lines[] = ['Otros tributos', $invoice->taxes]; }
        $p = $this->pdf;
        foreach ($lines as [$label, $amount]) {
            $p->SetX(110);
            $p->SetFont('Helvetica', '', 10);
            $p->Cell(55, 6, $this->text($label . ':'), 0, 0, 'R');
            $p->Cell(35, 6, $this->money($amount), 0, 1, 'R');
        }
        $p->SetX(110);
        $p->SetFont('Helvetica', 'B', 11);
        $p->Cell(55, 8, $this->text('Importe total:'), 'T', 0, 'R');
        $p->Cell(35, 8, $this->money($invoice->total), 'T', 1, 'R');
        if ($letter === 'B' && $invoice->vat > 0) {
            $p->Ln(2);
            $p->SetFont('Helvetica', '', 8);
            $p->SetX(self::LEFT);
            $p->Cell(self::WIDTH, 5, $this->text('Régimen de Transparencia Fiscal al Consumidor (Ley 27.743) - IVA contenido: ' . $this->money($invoice->vat)), 0, 1, 'R');
        }
    }

    private function footer(InvoiceResult $result, string $qrText): void
    {
        $p = $this->pdf;
        // El pie ocupa el margen inferior reservado: sin esto FPDF lo mandaria a una pagina nueva.
        $p->SetAutoPageBreak(false);
        $top = 250.0;
        $p->Line(self::LEFT, $top - 3, self::LEFT + self::WIDTH, $top - 3);
        // La matriz incluye el margen blanco (quiet zone) que los lectores necesitan alrededor del QR.
        $matrix = (new QRCode(new QROptions(['eccLevel' => EccLevel::M])))->addByteSegment($qrText)->getQRMatrix();
        $size = $matrix->getSize();
        $module = 40 / $size;
        $p->SetFillColor(0, 0, 0);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->check($x, $y)) { $p->Rect(self::LEFT + $x * $module, $top + $y * $module, $module, $module, 'F'); }
            }
        }
        $p->SetXY(52, $top + 4);
        $p->SetFont('Helvetica', 'B', 13);
        $p->Cell(70, 7, 'Comprobante Autorizado', 0, 2);
        $p->SetFont('Helvetica', '', 7);
        $p->MultiCell(70, 3.5, $this->text('Verificá este comprobante escaneando el código QR.'));
        $p->SetXY(125, $top + 4);
        $p->SetFont('Helvetica', 'B', 10);
        $p->Cell(30, 6, $this->text('CAE N°:'), 0, 0, 'R');
        $p->SetFont('Helvetica', '', 10);
        $p->Cell(45, 6, (string) $result->cae(), 0, 1);
        $p->SetX(125);
        $p->SetFont('Helvetica', 'B', 10);
        $p->Cell(30, 6, 'Fecha de Vto. de CAE:', 0, 0, 'R');
        $p->SetFont('Helvetica', '', 10);
        $p->Cell(45, 6, $this->date($result->vencimientoCae()), 0, 1);
    }

    /** @param list<array{0:string,1:string}> $lines */
    private function labelLines(float $width, array $lines): void
    {
        $p = $this->pdf;
        $x = $p->GetX();
        foreach ($lines as [$label, $value]) {
            if (trim($value) === '') { continue; }
            $p->SetX($x);
            $p->SetFont('Helvetica', 'B', 9);
            $labelWidth = $p->GetStringWidth($this->text($label . ': ')) + 1;
            $p->Cell($labelWidth, 5, $this->text($label . ':'));
            $p->SetFont('Helvetica', '', 9);
            $p->MultiCell($width - $labelWidth, 5, $this->text($value));
        }
    }

    /**
     * @param array<string,mixed> $datos
     * @return array<string,string>
     */
    private function section(array $datos, string $key): array
    {
        $section = $datos[$key] ?? [];
        if (!is_array($section)) { throw new ValidationException(["{$key} debe ser un array."]); }
        return array_map(static fn (mixed $value): string => trim((string) $value), $section);
    }

    /**
     * @param array<string,mixed> $datos
     * @return list<array{descripcion:string,cantidad:float,precio:float}>
     */
    private function items(array $datos, Invoice $invoice, string $letter): array
    {
        $items = [];
        foreach ((array) ($datos['items'] ?? []) as $item) {
            if (!is_array($item) || trim((string) ($item['descripcion'] ?? '')) === '' || !is_numeric($item['precio'] ?? null)) {
                throw new ValidationException(['Cada item requiere descripcion y precio.']);
            }
            $items[] = ['descripcion' => (string) $item['descripcion'], 'cantidad' => (float) ($item['cantidad'] ?? 1), 'precio' => (float) $item['precio']];
        }
        if ($items === []) { return []; }
        // Los items impresos tienen que cerrar con lo autorizado por ARCA.
        $expected = $letter === 'A' ? $invoice->total - $invoice->vat - $invoice->taxes : $invoice->total - $invoice->taxes;
        $sum = array_sum(array_map(static fn (array $item): float => $item['cantidad'] * $item['precio'], $items));
        if (abs($sum - $expected) > 0.01) {
            throw new ValidationException([sprintf('La suma de los items $%.2f no coincide con el comprobante autorizado $%.2f%s.', $sum, $expected, $letter === 'A' ? ' (en clase A los precios van sin IVA)' : '')]);
        }
        return $items;
    }

    private function text(string $value): string
    {
        // Las fuentes estandar de PDF usan Windows-1252.
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $value);
        return $converted === false ? $value : $converted;
    }

    private function money(float $amount): string { return '$ ' . number_format($amount, 2, ',', '.'); }
    private function quantity(float $value): string { return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ','); }
    private function rate(float $rate): string { return str_replace('.', ',', rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.')); }
    private function date(?DateTimeImmutable $date): string { return $date?->format('d/m/Y') ?? ''; }
}
