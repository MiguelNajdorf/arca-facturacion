# Homologacion paso a paso

Homologacion es el ambiente de pruebas. Sus comprobantes no tienen validez fiscal y sus certificados no sirven en produccion.

1. Ejecuta `php vendor/bin/arca install` y elige homologacion.
2. Revisa el paso pendiente con `php vendor/bin/arca instalacion:estado`. La CLI muestra la ruta real de la clave privada, la ruta real del CSR y el alias elegido.
3. Conserva `privada.key` solamente en tu computadora. No la subas a ARCA, no la compartas, no la envies por correo y no la subas a GitHub.
4. Ingresa a ARCA con CUIT y clave fiscal y abri **WSASS**. Si no aparece, adherilo desde el **Administrador de Relaciones de Clave Fiscal**.
5. En WSASS elegi **Nuevo Certificado** y escribi exactamente el alias real mostrado por el instalador.
6. Abri `solicitud.csr` con un editor de texto plano. Copia todo, incluyendo `-----BEGIN CERTIFICATE REQUEST-----` y `-----END CERTIFICATE REQUEST-----`.
7. Pega el contenido completo en el campo PKCS#10 o CSR, confirma y descarga el certificado.
8. Guarda el `.crt`, `.cer` o `.pem` sin cambiar su contenido e instalalo:

~~~bash
php vendor/bin/arca certificado:instalar "C:\ruta\al\certificado.crt"
php vendor/bin/arca instalacion:continuar
~~~

La gestion en WSASS ocurre fuera de arca_facturacion y la interfaz de ARCA puede cambiar. Usa el [manual oficial para crear el certificado](https://www.arca.gob.ar/ws/WSASS/html/crearcertificado.html) si los nombres o pantallas no coinciden.

Luego continua:

9. En WSASS usa **Crear Autorizacion a Servicio** para relacionar alias, CUIT representado y WSFE.
10. Configura el punto de venta de testing que corresponda a tu habilitacion.
11. Ejecuta `php vendor/bin/arca doctor --online`.
12. Ejecuta `php vendor/bin/arca factura:simular --total=15000`.
13. Revisa el paso 6 con `instalacion:continuar` y realiza una emision controlada en homologacion.

Si cerras la consola en cualquier momento:

~~~bash
php vendor/bin/arca instalacion:estado
php vendor/bin/arca instalacion:continuar
~~~

Referencias oficiales: [WSASS](https://www.arca.gob.ar/ws/WSASS/html/index.html), [certificados](https://www.arca.gob.ar/ws/documentacion/certificados.asp) y [factura electronica](https://www.arca.gob.ar/fe/).

La prueba PHPUnit real sigue siendo opt-in y solo consulta servicios:

~~~bash
ARCA_RUN_HOMOLOGATION=1 vendor/bin/phpunit --group homologation
~~~
