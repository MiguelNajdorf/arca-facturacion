# PDF y QR de la factura

ARCA exige que los comprobantes electronicos impresos lleven un codigo QR. Al escanearlo, se abre la pagina de ARCA con los datos del comprobante.

Hay dos formas de cumplirlo:

- `pdf()`: arma la factura completa en A4, lista para enviar o imprimir.
- `urlQr()`: te da solo el texto del QR, para que lo imprimas con tu propio diseno.

Las dos funcionan solo con comprobantes **autorizados** (con CAE). Requieren `composer install` y las extensiones `gd`, `mbstring` y `zlib`; `php vendor/bin/arca doctor` las revisa.

## PDF completo

~~~php
$resultado = $arca->emitir($factura, 'venta-45821');

$pdf = $arca->pdf($factura, $resultado, [
    'emisor' => [
        'razon_social' => 'Mi Empresa S.R.L.',          // obligatorio
        'domicilio' => 'Av. Siempreviva 742, Cordoba',
        'condicion_iva' => 'IVA Responsable Inscripto',
        'ingresos_brutos' => '901-123456-7',
        'inicio_actividades' => '2020-03-01',            // AAAA-MM-DD
    ],
    'receptor' => [
        'nombre' => 'Cliente S.A.',
        'domicilio' => 'Calle 1 123',
        // 'condicion_iva' => '...', si falta se usa la que informaste en la factura
    ],
    'items' => [
        ['descripcion' => 'Producto', 'cantidad' => 2, 'precio' => 5000],
        ['descripcion' => 'Envio', 'precio' => 5000],   // cantidad 1 por defecto
    ],
    'observaciones' => 'Pago a 30 dias.',
]);

file_put_contents('factura.pdf', $pdf);   // o enviarlo al navegador, adjuntarlo a un mail, etc.
~~~

`pdf()` devuelve el contenido del PDF como texto. Para mostrarlo en el navegador:

~~~php
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="factura.pdf"');
echo $pdf;
~~~

Que incluye:

- Recuadro con la letra (A, B o C) y el codigo del comprobante.
- Emisor: razon social, domicilio, condicion frente al IVA, CUIT, Ingresos Brutos e inicio de actividades.
- Punto de venta, numero y fecha de emision.
- Periodo facturado y vencimiento del pago, si es de servicios.
- Receptor: documento, nombre, condicion frente al IVA y domicilio. Moneda y cotizacion si no es en pesos.
- Items con cantidad, precio unitario y subtotal.
- Totales. En clase A: neto gravado, IVA por alicuota, no gravado, exento y tributos. En clase B con IVA: la leyenda del IVA contenido (Regimen de Transparencia Fiscal al Consumidor, Ley 27.743).
- Pie con el QR, "Comprobante Autorizado", CAE y su vencimiento.

### Items

- En **clase A** el precio va **sin IVA**: los items suman el neto (mas no gravado y exento).
- En **clase B y C** el precio es el **final**: los items suman el total.
- Si no cierran con lo que autorizo ARCA, `pdf()` lanza un error en lugar de imprimir algo distinto a lo facturado.
- Los items son opcionales. Sin items, el PDF muestra solo los totales.

### Limites

- Una sola pagina de pie: si hay tantos items que pasa a una segunda hoja, el QR y el CAE quedan en la ultima.
- No incluye logo.
- Soporta Facturas y Notas de credito A, B y C.

## Solo el QR

~~~php
$texto = $arca->urlQr($factura, $resultado);
// https://www.arca.gob.ar/fe/qr/?p=eyJ2ZXIiOjEsImZlY2hhIjoi...
~~~

Genera la imagen con cualquier libreria de QR usando ese texto. Sigue la [especificacion oficial de ARCA](https://www.arca.gob.ar/fe/qr/documentos/QRespecificaciones.pdf): JSON version 1 codificado en Base64, con fecha, CUIT del emisor, punto de venta, tipo y numero de comprobante, importe, moneda, cotizacion, documento del receptor (salvo consumidor final sin identificar) y CAE.
