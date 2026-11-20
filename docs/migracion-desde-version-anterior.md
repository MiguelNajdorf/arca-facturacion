# Migracion desde la version anterior

## Reutilizado

- validacion CUIT;
- firma CMS;
- endpoints separados;
- cache de tickets;
- TLS y timeouts;
- mapeo central de WSFE;
- validaciones de importes;
- locks fiscales.

## Reemplazado

- ArcaReutilizable por ArcaFacturacion;
- ConfigStore y config/arca.json por ArcaConfig + .env;
- ArcaFiscalClient por WsaaClient y SoapArcaGateway;
- ReceiptStore JSON por SQLiteReceiptRepository;
- arrays publicos por Invoice, InvoiceBuilder e InvoiceResult;
- endpoints HTTP de emision por API PHP;
- RuntimeException general por excepciones especificas.

## Ejemplo

Antes se construia InvoiceService y se enviaba un array con codigos WSFE. Ahora:

~~~php
$arca = ArcaFacturacion\Arca::desdeEntorno();
$resultado = $arca->emitir(
    $arca->facturaC()->consumidorFinal()->importeTotal(15000),
    'venta-45821'
);
~~~

No hay compatibilidad hacia atras. Migra cada payload hacia el builder o instancia Invoice para casos avanzados.

## Aun no incluido

- CAEA;
- regimenes especiales fuera de WSFEv1;
- PDF/QR de representacion;
- libro IVA y contabilidad;
- decisiones tributarias automaticas.
