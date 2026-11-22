# Certificados y autorizacion

## Que genera arca_facturacion

~~~bash
php vendor/bin/arca install
~~~

El asistente crea una clave privada RSA de 2048 bits y una solicitud PKCS#10:

- `privada.key` es secreta y queda en tu equipo. No se sube a ARCA, no se envia por correo y no se versiona.
- `solicitud.csr` es el archivo que se presenta en ARCA para obtener el certificado.

Sin la clave privada, el certificado descargado no puede usarse. Hace un respaldo seguro de la clave.

## Homologacion: WSASS

Antes de ingresar a ARCA, ejecuta `instalacion:estado`. El paso 3 muestra las rutas reales de `privada.key` y `solicitud.csr`, junto con el alias real elegido durante la instalacion.

La clave privada queda solamente en tu computadora. No la subas a ARCA, no la compartas, no la envies por correo y no la subas a GitHub. El archivo que debes utilizar en WSASS es `solicitud.csr`.

1. Ingresa al sitio oficial de ARCA con tu CUIT y clave fiscal.
2. Abri el servicio **WSASS**.
3. Si WSASS no aparece entre tus servicios, adherilo desde el **Administrador de Relaciones de Clave Fiscal**.
4. Dentro de WSASS, elegi **Nuevo Certificado**.
5. En nombre simbolico o alias, escribi exactamente el alias que muestra `instalacion:estado`. Ese valor es dinamico y corresponde a tu instalacion; no uses un alias copiado de otra guia.
6. Abri la ruta real de `solicitud.csr` con un editor de texto plano, como Bloc de notas o Visual Studio Code.
7. Copia el contenido completo, incluidas las lineas `-----BEGIN CERTIFICATE REQUEST-----` y `-----END CERTIFICATE REQUEST-----`.
8. Pega todo en el campo de solicitud PKCS#10 o CSR de WSASS.
9. Confirma la creacion.
10. Descarga el certificado generado por ARCA.
11. Guardalo en una ubicacion facil de encontrar. Puede tener extension `.crt`, `.cer` o `.pem`. No cambies manualmente su contenido.

Cuando lo tengas, instala exactamente el archivo descargado:

~~~bash
php vendor/bin/arca certificado:instalar "C:\ruta\al\certificado.crt"
php vendor/bin/arca instalacion:continuar
~~~

Documentacion oficial: [certificados de homologacion](https://www.arca.gob.ar/ws/documentacion/certificados.asp), [crear certificado en WSASS](https://www.arca.gob.ar/ws/WSASS/html/crearcertificado.html).

Este paso ocurre fuera de arca_facturacion. La interfaz de ARCA puede cambiar; si una pantalla no coincide, consulta primero el manual oficial.

## Produccion: Administracion de Certificados Digitales

1. Ingresa con clave fiscal.
2. Abri **Administracion de Certificados Digitales**.
3. Selecciona a la persona o empresa representada correcta.
4. Busca **Agregar Alias**.
5. Escribi el alias de produccion y sube su CSR de produccion.
6. Descarga el archivo CRT emitido.

Guia oficial: [Generacion de Certificados Digitales para Produccion](https://www.arca.gob.ar/ws/WSAA/WSAA.ObtenerCertificado.pdf).

Los nombres pueden cambiar. Si la pantalla no coincide, no adivines: volve a la [pagina oficial de certificados](https://www.arca.gob.ar/ws/documentacion/certificados.asp).

## Instalar

~~~bash
php vendor/bin/arca certificado:instalar "C:\ruta\al\certificado.crt"
~~~

Se comprueba que sea X.509, este vigente y corresponda a la clave privada del ambiente activo. Se aceptan normalmente `.crt`, `.cer` y `.pem`.

## Autorizar WSFE

Instalar el certificado no alcanza. Todavia hay que autorizar el servicio de facturacion.

En homologacion, dentro de WSASS usa **Crear Autorizacion a Servicio**. Elegi el alias, completa el CUIT representado y selecciona el servicio WSFE/facturacion electronica disponible.

En produccion, usa **Administrador de Relaciones de Clave Fiscal**, crea la relacion para el Webservice de Facturacion Electronica y selecciona el computador fiscal/alias como representante.

Guias oficiales: [autorizacion en WSASS](https://www.arca.gob.ar/ws/WSASS/html/crearautorizacion.html) y [delegacion de Webservices en produccion](https://www.arca.gob.ar/ws/WSAA/ADMINREL.DelegarWS.pdf).

Despues valida realmente la relacion:

~~~bash
php vendor/bin/arca doctor --online
~~~

El progreso solo marca autorizacion y conexion cuando ARCA responde correctamente.

## Regeneracion protegida

El comando normal nunca sobrescribe:

~~~bash
php vendor/bin/arca certificado:generar
~~~

Si ya existe una clave, se detiene. `--force` requiere confirmacion interactiva escribiendo `REGENERAR`; en automatizacion tambien requiere `--yes`. Una clave nueva deja de coincidir con el certificado anterior.
