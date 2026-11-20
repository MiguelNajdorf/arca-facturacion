# Errores

La jerarquia parte de ArcaFacturacionException:

- ConfigurationException
- CertificateException
- AuthenticationException
- ValidationException
- ConnectivityException
- ArcaResponseException
- StorageException
- DuplicateInvoiceException

~~~php
try {
    $resultado = $arca->emitir($factura, 'venta-45821');
} catch (ArcaFacturacion\Exception\ValidationException $error) {
    foreach ($error->errores() as $mensaje) {
        echo $mensaje;
    }
} catch (ArcaFacturacion\Exception\AuthenticationException $error) {
    echo 'No fue posible autenticarse con ARCA.';
}
~~~

mensajeHumano() es apto para la persona que integra. codigo() y detalleTecnico() son herramientas de diagnostico y no deben enviarse automaticamente a usuarios web.

Un resultado unknown significa que no puede confirmarse si ARCA proceso el pedido. No vuelvas a emitir a ciegas: usa operaciones:recuperar con el operation_id.
