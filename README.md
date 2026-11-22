# arca_facturacion

Facturacion electronica de ARCA (ex AFIP) para cualquier programa PHP.

Resuelve certificados, autenticacion WSAA, conexion WSFE, numeracion, pedido de CAE, validaciones, guardado en SQLite y recuperacion de operaciones que quedaron a medias. Un asistente te guia en la configuracion inicial, que se hace una sola vez.

> Proyecto independiente: no esta afiliado ni respaldado por ARCA. No decide que comprobante corresponde legalmente ni reemplaza a tu contador.

## Como se usa, en resumen

1. Lo agregas a tu proyecto con `composer require arca-facturacion/arca-facturacion`.
2. Corres el asistente (`php vendor/bin/arca install`). Te pide CUIT y punto de venta, genera la clave y te dice que hacer en la web de ARCA.
3. Desde tu programa llamas a `emitir()`. Te devuelve el CAE.
4. Si queres, `pdf()` te arma la factura en PDF con el QR obligatorio de ARCA.

Toda la configuracion, la clave privada, los certificados y el registro de facturas quedan en la carpeta `arca/` de tu proyecto, fuera de `vendor/`: un `composer update` no los toca.

## Requisitos

- PHP 8.1 o superior, para consola y tambien para el servidor web si tu programa es web.
- Extensiones de PHP: `soap`, `openssl`, `simplexml`, `json`, `pdo_sqlite`, `sqlite3`, `gd`, `mbstring` y `zlib`. Composer avisa si falta alguna.
- [Composer](https://getcomposer.org).
- CUIT con clave fiscal para entrar a ARCA.
- Salida a internet por HTTPS hacia los servidores de ARCA.

## Paso 1: instalar

En la carpeta de tu proyecto:

~~~bash
composer require arca-facturacion/arca-facturacion
~~~

Todos los comandos de esta guia se ejecutan **desde esa carpeta**. Para revisar el entorno en cualquier momento:

~~~bash
php vendor/bin/arca doctor
~~~

La primera vez que corras un comando se crea la carpeta `arca/` para los datos privados, con un `.gitignore` que impide subirlos al repositorio y un `.htaccess` que bloquea su descarga en Apache.

> **Importante:** si la raiz de tu proyecto es tambien la raiz publica del sitio (por ejemplo, PHP plano sin carpeta `public/`) y no usas Apache, `arca/` podria quedar descargable desde internet. En ese caso guarda los datos fuera de la web con la variable de entorno `ARCA_DIR`, por ejemplo `ARCA_DIR=/home/usuario/datos-arca`.

### Alternativa: carpeta aparte, sin Composer en tu proyecto

Si tu programa no usa Composer, clona esta libreria en una carpeta propia:

~~~bash
git clone https://github.com/MiguelNajdorf/arca-facturacion.git arca_facturacion
cd arca_facturacion
composer install --no-dev
~~~

En esa forma los comandos son `php bin/arca ...` en lugar de `php vendor/bin/arca ...`, los datos quedan en `.env` y `storage/` de esa carpeta, y tu programa la carga como se explica en el [Paso 3](#desde-una-carpeta-aparte). Composer solo hace falta para el PDF; sin el, facturar funciona igual.

## Paso 2: configurar en homologacion (pruebas)

Homologacion es el ambiente de pruebas de ARCA: las facturas no tienen validez fiscal. Empeza siempre por aca.

### 2.1 Correr el asistente

~~~bash
php vendor/bin/arca install
~~~

Te explica cada dato antes de pedirlo. Elegi **homologacion**, tu CUIT, el punto de venta y un alias, que es un nombre para reconocer la conexion dentro de ARCA (por ejemplo `mi-sistema-pruebas`).

El asistente genera dos archivos en `storage/certificados/homologacion/`:

- `privada.key`: **secreta**. No se sube a ARCA, no se manda por mail y no se sube a GitHub. Hace un respaldo.
- `solicitud.csr`: es la que vas a pegar en ARCA.

Despues se pausa, porque el paso siguiente se hace en la web de ARCA. Podes cerrar la consola sin problema. Para ver en que paso estas o retomar:

~~~bash
php vendor/bin/arca instalacion:estado
php vendor/bin/arca instalacion:continuar
~~~

### 2.2 Pedir el certificado en ARCA (WSASS)

1. Entra a [arca.gob.ar](https://www.arca.gob.ar) con CUIT y clave fiscal.
2. Abri el servicio **WSASS**. Si no aparece, adheri el servicio desde **Administrador de Relaciones de Clave Fiscal**.
3. Elegi **Nuevo Certificado**.
4. Como nombre o alias, escribi exactamente el alias que muestra `php vendor/bin/arca instalacion:estado`.
5. Abri `solicitud.csr` con el Bloc de notas y copia **todo**, incluidas las lineas `-----BEGIN CERTIFICATE REQUEST-----` y `-----END CERTIFICATE REQUEST-----`.
6. Pegalo en el campo de la solicitud (PKCS#10 / CSR) y confirma.
7. Descarga el certificado que te da ARCA (`.crt`, `.cer` o `.pem`) y no lo edites.

Guia oficial: [crear certificado en WSASS](https://www.arca.gob.ar/ws/WSASS/html/crearcertificado.html).

### 2.3 Instalar el certificado

~~~bash
php vendor/bin/arca certificado:instalar "C:\Descargas\certificado.crt"
~~~

El comando comprueba que el certificado sea valido, este vigente y corresponda a tu clave privada.

### 2.4 Autorizar la facturacion (WSFE)

Tener el certificado no alcanza: hay que darle permiso para facturar.

1. En WSASS elegi **Crear Autorizacion a Servicio**.
2. Selecciona tu alias, el CUIT representado y el servicio **wsfe** (facturacion electronica).

Guia oficial: [autorizacion en WSASS](https://www.arca.gob.ar/ws/WSASS/html/crearautorizacion.html).

### 2.5 Comprobar la conexion

~~~bash
php vendor/bin/arca doctor --online
~~~

Se conecta con ARCA y confirma que el certificado y la autorizacion funcionan. Si algo falla, el mensaje dice que corregir.

### 2.6 Simular y hacer una factura de prueba

~~~bash
php vendor/bin/arca factura:simular --total=15000
php vendor/bin/arca factura:probar --total=15000
~~~

`simular` valida la factura sin enviar nada. `probar` la emite de verdad en el ambiente activo; en homologacion no tiene validez fiscal.

Si `probar` te devuelve un CAE, la configuracion esta lista.

## Paso 3: facturar desde tu programa

Funciona con cualquier PHP: Laravel, CodeIgniter, PHP plano, etc. `desdeEntorno()` encuentra solo la carpeta `arca/`.

~~~php
<?php
require __DIR__ . '/vendor/autoload.php';   // ya lo carga tu framework si usas uno

use ArcaFacturacion\Arca;
use ArcaFacturacion\Exception\ArcaFacturacionException;

try {
    $arca = Arca::desdeEntorno();

    $factura = $arca->facturaC()
        ->consumidorFinal()
        ->conceptoProductos()
        ->importeTotal(15000);

    // Primero, opcional: valida sin enviar nada a ARCA
    // print_r($arca->simular($factura));

    // El segundo dato es un identificador unico de TU venta (ver abajo)
    $resultado = $arca->emitir($factura, 'venta-45821');

    if ($resultado->fueAutorizado()) {
        echo 'CAE: ' . $resultado->cae() . PHP_EOL;
        echo 'Vence: ' . $resultado->vencimientoCae()?->format('d/m/Y') . PHP_EOL;
        echo 'Numero: ' . $resultado->numeroComprobante() . PHP_EOL;
    } elseif ($resultado->fueRechazado()) {
        print_r($resultado->errores());
    } elseif ($resultado->esIncierto()) {
        // ARCA no respondio claro (corte de red, timeout). NO reintentes con otra clave:
        // guarda $resultado->operationId() y reconcilia con $arca->recuperar($id)
        // o con: php vendor/bin/arca operaciones:recuperar ID
    }
} catch (ArcaFacturacionException $error) {
    echo $error->mensajeHumano();
}
~~~

### Desde una carpeta aparte

Si clonaste la libreria en su propia carpeta, carga su `bootstrap.php` y pasale la ruta a `desdeEntorno()`:

~~~php
$rutaArca = 'C:/facturacion/arca_facturacion';
require $rutaArca . '/bootstrap.php';
$arca = ArcaFacturacion\Arca::desdeEntorno($rutaArca);
~~~

El resto del codigo es igual.

### La clave de la venta (idempotencia)

El segundo parametro de `emitir()` es **un identificador unico de la venta en tu sistema**, por ejemplo el ID del pedido. Sirve para no facturar dos veces la misma venta:

- Si llamas dos veces a `emitir()` con la misma clave, la segunda no crea otra factura: devuelve la primera (`$resultado->esReutilizado()` da `true`).
- No uses una clave aleatoria ni la fecha y hora. Si se corta la conexion y reintentas, se emitiria una factura duplicada.

### Otros comprobantes

| Comprobante | Metodo | Ejemplo |
|---|---|---|
| Factura A / B / C | `facturaA()`, `facturaB()`, `facturaC()` | [examples/04_emitir_factura_b.php](examples/04_emitir_factura_b.php) |
| Nota de credito A / B / C | `notaCreditoA()`, `notaCreditoB()`, `notaCreditoC()` | [examples/07_nota_credito.php](examples/07_nota_credito.php) |
| Cliente con CUIT | `->clienteCuit('30712345671')` | [docs/factura-b.md](docs/factura-b.md) |
| IVA (0, 2,5, 5, 10,5, 21 o 27 %), una o varias alicuotas | `->netoGravado(15000)->iva(21, 10000)->iva(10.5, 5000)` | [docs/factura-b.md](docs/factura-b.md) |
| Servicios | `->conceptoServicios($desde, $hasta, $vencimiento)` | [docs/factura-c.md](docs/factura-c.md) |
| Consultar una factura emitida | `$arca->consultarComprobante($tipo, $numero)` | [examples/05_consultar_comprobante.php](examples/05_consultar_comprobante.php) |
| Ultimo numero emitido | `$arca->ultimoComprobante($tipo)` | [examples/06_ultimo_comprobante.php](examples/06_ultimo_comprobante.php) |

En [examples/](examples/) hay ejemplos listos para correr. Los que emiten estan bloqueados hasta definir `ARCA_EXAMPLE_EMIT=1`.

### PDF con el QR de ARCA

ARCA exige que la factura impresa lleve un codigo QR. Despues de emitir, `pdf()` arma la factura en A4 con letra y codigo, datos del emisor y del receptor, items, totales con el IVA por alicuota, CAE con su vencimiento y el QR:

~~~php
$resultado = $arca->emitir($factura, 'venta-45821');

if ($resultado->fueAutorizado()) {
    $pdf = $arca->pdf($factura, $resultado, [
        'emisor' => [
            'razon_social' => 'Mi Empresa S.R.L.',
            'domicilio' => 'Av. Siempreviva 742, Cordoba',
            'condicion_iva' => 'IVA Responsable Inscripto',
            'ingresos_brutos' => '901-123456-7',
            'inicio_actividades' => '2020-03-01',
        ],
        'receptor' => ['nombre' => 'Cliente S.A.', 'domicilio' => 'Calle 1 123'],
        'items' => [
            ['descripcion' => 'Producto', 'cantidad' => 2, 'precio' => 7500],
        ],
    ]);
    file_put_contents("factura-{$resultado->numeroComprobante()}.pdf", $pdf);
}
~~~

Los items tienen que sumar lo que autorizo ARCA (en clase A, sin IVA): si no cierran, `pdf()` avisa en vez de imprimir algo distinto a lo facturado.

Si preferis tu propio diseno, `$arca->urlQr($factura, $resultado)` te da el texto exacto que tiene que llevar el QR. Todo el detalle esta en [docs/pdf.md](docs/pdf.md).

## Paso 4: pasar a produccion

**No alcanza con cambiar una variable.** Produccion necesita su propia clave, certificado, autorizacion y punto de venta.

~~~bash
php vendor/bin/arca produccion:preparar
~~~

Genera clave, CSR, configuracion y base de datos de produccion sin tocar lo de homologacion. Despues, en ARCA:

1. Pedi el certificado en **Administracion de Certificados Digitales**, subiendo el CSR de **produccion**.
2. Autoriza el servicio de facturacion electronica en **Administrador de Relaciones de Clave Fiscal**.
3. Crea un punto de venta para **Web Services** en **Administracion de Puntos de Venta y Domicilios**. No reutilices uno que use otro sistema.

Luego:

~~~bash
php vendor/bin/arca certificado:instalar "C:\Descargas\certificado-produccion.crt"
php vendor/bin/arca ambiente:cambiar produccion
php vendor/bin/arca doctor --online
~~~

Para volver a pruebas: `php vendor/bin/arca ambiente:cambiar homologacion`. Detalles y checklist en [docs/produccion.md](docs/produccion.md).

## Alternativa: asistente web

Si preferis no usar la consola, el mismo asistente existe en version web, solo para uso local y solo en la forma de **carpeta aparte** (instalado con Composer queda dentro de `vendor/` y no se publica).

1. Configura Laragon, XAMPP o Apache con `public/` de esta carpeta como raiz del sitio.
2. Abri `http://arca_facturacion.test/install/`. Tambien sirven `.local`, `localhost` o `127.0.0.1`.
3. Al terminar, **borra la carpeta `public/install/`**.

Consola y web comparten el progreso: podes empezar en una y seguir en la otra. Mas detalles en [docs/instalacion.md](docs/instalacion.md).

## Todos los comandos

| Comando | Para que sirve |
|---|---|
| `php vendor/bin/arca install` | Inicia o retoma la configuracion guiada |
| `php vendor/bin/arca instalacion:estado` | Muestra en que paso estas y que sigue |
| `php vendor/bin/arca instalacion:continuar` | Retoma el paso pendiente |
| `php vendor/bin/arca doctor [--online]` | Revisa PHP, configuracion y, con `--online`, la conexion con ARCA |
| `php vendor/bin/arca certificado:instalar RUTA` | Instala el certificado descargado de ARCA |
| `php vendor/bin/arca certificado:generar` | Genera clave y CSR; nunca pisa una clave existente |
| `php vendor/bin/arca factura:simular --total=N` | Valida una Factura C sin enviarla |
| `php vendor/bin/arca factura:probar --total=N` | Emite una Factura C en el ambiente activo |
| `php vendor/bin/arca produccion:preparar` | Prepara produccion sin tocar homologacion |
| `php vendor/bin/arca ambiente:cambiar AMBIENTE` | Cambia entre `homologacion` y `produccion` |
| `php vendor/bin/arca operaciones:listar` | Lista las ultimas operaciones |
| `php vendor/bin/arca operaciones:recuperar ID` | Resuelve una operacion que quedo incierta |

## Que respaldar y que no subir nunca

- La carpeta `arca/` (o `.env` y `storage/` si usas carpeta aparte) contiene tu configuracion, la **clave privada** y el registro de facturas. Respaldala y **no la subas a GitHub** (su `.gitignore` ya la excluye).
- Si perdes `privada.key`, el certificado deja de servir y hay que pedir uno nuevo en ARCA.
- El certificado vence: agenda la fecha. `php vendor/bin/arca doctor` la muestra.

## Problemas frecuentes

- **"Falta ARCA_CUIT. Ejecuta: php vendor/bin/arca install"**: no corriste el asistente, o `php vendor/bin/arca doctor` muestra otra "Carpeta de datos" que la que esperabas (revisa `ARCA_DIR`). En la forma de carpeta aparte, revisa la ruta que le pasas a `desdeEntorno()`.
- **Falla `doctor --online`**: casi siempre falta la autorizacion WSFE (paso 2.4) o el alias no coincide con el de ARCA.
- **Funciona en consola pero no desde la web**: la extension `soap` o `pdo_sqlite` esta activada en el PHP de consola pero no en el del servidor web.

Mas casos en [docs/errores.md](docs/errores.md) y [docs/preguntas-frecuentes.md](docs/preguntas-frecuentes.md).

## Documentacion

- [Instalacion detallada](docs/instalacion.md), [certificados](docs/certificados.md), [homologacion](docs/homologacion.md) y [produccion](docs/produccion.md)
- [Facturas C](docs/factura-c.md), [A y B](docs/factura-b.md) y [notas de credito](docs/notas-de-credito.md)
- [PDF y QR](docs/pdf.md)
- [Errores](docs/errores.md), [seguridad](docs/seguridad.md) y [persistencia](docs/persistencia.md)
- [Arquitectura](docs/arquitectura.md)

## Para desarrollar

~~~bash
composer install
composer check
~~~

`composer check` corre lint, PHPUnit, PHPStan y el chequeo de secretos. Los tests no se conectan con ARCA.

## Licencia

MIT. Libre para usar, modificar y distribuir.
