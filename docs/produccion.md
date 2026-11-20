# Pasar de homologacion a produccion

No cambies solamente `ARCA_ENVIRONMENT`. Produccion necesita credenciales, autorizaciones, punto de venta y almacenamiento propios.

## Preparar el perfil

~~~bash
php bin/arca produccion:preparar
~~~

El asistente explica el cambio, pide el punto de venta real y el alias de produccion, y crea:

- `storage/certificados/produccion/privada.key`;
- `storage/certificados/produccion/solicitud.csr`;
- `storage/configuracion/produccion.env`;
- `storage/arca-produccion.sqlite`;
- tickets, locks y logs bajo carpetas de produccion.

La clave, el perfil y la base de homologacion permanecen intactos.

## Tramites en ARCA

1. Gestiona el certificado con **Administracion de Certificados Digitales** y el CSR de produccion.
2. Autoriza el Webservice de Facturacion Electronica mediante **Administrador de Relaciones de Clave Fiscal**.
3. Habilita un punto de venta especifico para Web Services mediante **Administracion de Puntos de Venta y Domicilios**. Debe ser distinto de los usados por otros sistemas de emision.
4. Instala el certificado de produccion.

Referencias: [certificados oficiales](https://www.arca.gob.ar/ws/documentacion/certificados.asp), [generacion en produccion](https://www.arca.gob.ar/ws/WSAA/WSAA.ObtenerCertificado.pdf), [delegacion](https://www.arca.gob.ar/ws/WSAA/ADMINREL.DelegarWS.pdf) y [factura electronica](https://www.arca.gob.ar/fe/).

La interfaz administrativa puede cambiar. El asistente nombra solo servicios y opciones documentados oficialmente y enlaza la fuente para comprobar el recorrido vigente.

## Validar antes de emitir

~~~bash
php bin/arca doctor --online
php bin/arca factura:simular --total=15000
php bin/arca instalacion:continuar
~~~

`factura:probar` en produccion crea un comprobante fiscal real y exige escribir `EMITIR`, salvo uso automatizado explicito con `--yes`.

## Cambiar entre perfiles preparados

~~~bash
php bin/arca ambiente:cambiar homologacion
php bin/arca ambiente:cambiar produccion
~~~

El comando copia a `.env` el perfil privado elegido. No mueve ni reemplaza certificados, bases o tickets.

## Checklist operativo

- respaldo cifrado de claves, certificados y bases;
- `doctor --online` sin errores;
- carpeta `public/install/` eliminada o renombrada;
- document root en `public/`;
- permisos restrictivos en `storage/`;
- clave de idempotencia estable por venta;
- procedimiento para reconciliar estados `unknown`;
- alerta de vencimiento del certificado;
- prueba controlada antes de integrar el flujo definitivo.
