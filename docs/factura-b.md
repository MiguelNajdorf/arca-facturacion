# Facturas A y B (con IVA)

Una sola alicuota:

~~~php
$factura = $arca->facturaB()
    ->clienteCuit('30712345671', vatCondition: 1)
    ->conceptoProductos()
    ->netoGravado(10000)
    ->iva(21, 10000)          // alicuota, base imponible; el importe (2100) se calcula
    ->importeTotal(12100);

$resultado = $arca->emitir($factura, 'venta-45822');
~~~

Varias alicuotas en el mismo comprobante: agrega una linea `iva()` por cada una. El neto gravado es la suma de las bases.

~~~php
$factura = $arca->facturaA()
    ->clienteCuit('30712345671')
    ->netoGravado(15000)
    ->iva(21, 10000)          // 2100
    ->iva(10.5, 5000)         // 525
    ->importeTotal(17625);    // 15000 + 2100 + 525
~~~

`iva(alicuota, base, importe)`:

- **alicuota**: `0`, `2.5`, `5`, `10.5`, `21` o `27`, las que acepta WSFE. Otra alicuota se rechaza antes de enviar.
- **base**: base imponible de esa alicuota.
- **importe** (opcional): si tu sistema ya calculo el IVA, pasalo para usar exactamente ese valor. Si no, se calcula como `base * alicuota / 100`, redondeado a 2 decimales.

`iva21(importe)` sigue disponible como atajo para una unica alicuota del 21% sobre todo el neto.

Antes de usar la red se valida:

- el CUIT del receptor;
- que total = neto + IVA + no gravado + exento + tributos;
- que la suma de los importes de IVA sea el IVA total;
- que la suma de las bases sea el neto gravado.

La libreria no decide la condicion fiscal del receptor ni que alicuota corresponde a cada producto: usa los valores vigentes que correspondan.
