# arca_facturacion

La forma guiada de integrar facturacion electronica de ARCA en proyectos PHP.

arca_facturacion resuelve configuracion, certificados, autenticacion WSAA, conexion WSFE, numeracion, solicitud de CAE, validaciones, persistencia SQLite, idempotencia y recuperacion de operaciones inciertas.

Solo necesitas completar una vez la configuracion administrativa dentro de ARCA.

> Proyecto independiente: no esta afiliado, respaldado ni mantenido por ARCA. No decide que comprobante corresponde legalmente ni reemplaza asesoramiento contable.

## Requisitos

- PHP 8.1 o superior.
- Composer.
- Extensiones soap, openssl, SimpleXML, json, pdo_sqlite y sqlite3.
- Acceso HTTPS a los servicios de ARCA para autenticar y emitir.

## Instalacion por consola

~~~bash
composer install
php bin/arca install
php bin/arca instalacion:estado
~~~

El asistente explica cada concepto antes de preguntar, genera una clave privada y un CSR, guarda el progreso y se detiene cuando tenes que operar dentro de ARCA. Podes cerrar la terminal y continuar sin empezar de nuevo:

~~~bash
php bin/arca instalacion:continuar
php bin/arca certificado:instalar ruta/al/certificado.crt
php bin/arca doctor --online
php bin/arca factura:simular --total=15000
~~~

Para pasar a produccion no cambies solamente una variable. Usa `php bin/arca produccion:preparar`: crea claves, certificado, perfil, SQLite, tickets y logs separados sin sobrescribir homologacion.

## Instalacion web

La interfaz web es una alternativa completa a la consola y usa el mismo nucleo y el mismo archivo de progreso.

1. Ejecuta `composer install` si las dependencias todavia no estan instaladas.
2. Abri el proyecto en Laragon, Apache o un servidor PHP local con `public/` como document root.
3. Ingresa a `http://arca_facturacion.local/install/` (tambien admite `.test`, `localhost` y loopback).
4. Segui los pasos del asistente. No necesita usuario, contrasena, token ni variables para habilitarse.
5. Al terminar, elimina manualmente `public/install/`.

Podes comenzar por la web y continuar con `php bin/arca instalacion:continuar`, o comenzar por la CLI y retomar en `/install/`. Eliminar `public/install/` deshabilita solamente la interfaz web: la CLI, la configuracion, los certificados, SQLite y la facturacion siguen funcionando.

## Primera factura desde PHP

~~~php
<?php
require __DIR__ . '/vendor/autoload.php';

use ArcaFacturacion\Arca;

$arca = Arca::desdeEntorno();
$factura = $arca->facturaC()
    ->consumidorFinal()
    ->conceptoProductos()
    ->importeTotal(15000);

$resultado = $arca->emitir($factura, 'venta-45821');
echo $resultado->cae();
~~~

Prueba primero con simular(): valida y construye el pedido, pero no envia nada.

## Documentacion

- [Primeros pasos](docs/primeros-pasos.md)
- [Instalacion](docs/instalacion.md)
- [Certificados](docs/certificados.md)
- [Homologacion](docs/homologacion.md) y [produccion](docs/produccion.md)
- [Facturas C](docs/factura-c.md), [B](docs/factura-b.md) y [notas de credito](docs/notas-de-credito.md)
- [Errores](docs/errores.md), [seguridad](docs/seguridad.md) y [persistencia](docs/persistencia.md)
- [Arquitectura](docs/arquitectura.md) y [migracion](docs/migracion-desde-version-anterior.md)
- [Preguntas frecuentes](docs/preguntas-frecuentes.md)

Los ejemplos ejecutables estan en examples/.

## Calidad

~~~bash
composer check
~~~

Las pruebas normales no se conectan con ARCA. La prueba de homologacion es opt-in y nunca emite comprobantes.

## Licencia

MIT. arca_facturacion es gratuito y de codigo abierto.
