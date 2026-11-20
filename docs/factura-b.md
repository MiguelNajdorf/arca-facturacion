# Factura B

~~~php
$factura = $arca->facturaB()
    ->clienteCuit('30712345678', vatCondition: 1)
    ->conceptoProductos()
    ->netoGravado(10000)
    ->iva21(2100)
    ->importeTotal(12100);

$resultado = $arca->emitir($factura, 'venta-45822');
~~~

El CUIT se valida antes de usar la red. Total, neto, IVA, no gravado, exento y tributos deben cerrar. La libreria no decide la condicion fiscal del receptor: usa el valor vigente que corresponda.
