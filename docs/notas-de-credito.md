# Notas de credito

Las notas de credito A, B y C exigen el comprobante original.

~~~php
$nota = $arca->notaCreditoC()
    ->consumidorFinal()
    ->importeTotal(1500)
    ->comprobantesAsociados([[
        'type' => 11,
        'sales_point' => 3,
        'number' => 125,
        'date' => '2026-07-20',
    ]]);

$resultado = $arca->emitir($nota, 'devolucion-45821');
~~~

No se inventan asociaciones. Punto de venta, tipo y numero deben corresponder al comprobante real.
