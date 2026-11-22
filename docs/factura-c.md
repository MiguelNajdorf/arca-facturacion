# Factura C

Caso comun:

~~~php
require __DIR__ . '/vendor/autoload.php';
$arca = ArcaFacturacion\Arca::desdeEntorno();
$factura = $arca->facturaC()
    ->consumidorFinal()
    ->conceptoProductos()
    ->importeTotal(15000);

$vistaPrevia = $arca->simular($factura);
$resultado = $arca->emitir($factura, 'venta-45821');
~~~

La clase C no discrimina IVA. El preset de consumidor final usa documento 99, numero 0 y condicion IVA 5; confirma las tablas vigentes con condicionesIva('C') cuando tu integracion lo necesite.

Para servicios usa conceptoServicios(fechaDesde, fechaHasta, vencimientoPago).
