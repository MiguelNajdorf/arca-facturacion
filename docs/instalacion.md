# Instalacion guiada

## Comenzar por CLI

~~~bash
composer install
php bin/arca install
~~~

El asistente no presupone experiencia con ARCA. Antes de cada pregunta explica que significa el dato, por que se necesita y que resultado se espera.

El recorrido tiene seis etapas:

1. comprobar PHP, Composer y extensiones;
2. elegir ambiente, CUIT, punto de venta y alias;
3. generar la clave privada y el CSR, y obtener el certificado en ARCA;
4. instalar el certificado y autorizar WSFE;
5. validar la conexion y simular;
6. realizar una primera emision controlada.

## Homologacion o produccion

Homologacion es para aprender y probar. No genera comprobantes fiscales reales. Produccion es el ambiente real y sus emisiones tienen validez fiscal.

Comenza en homologacion si es tu primera integracion. Los ambientes no comparten certificado: una credencial de homologacion no sirve en produccion.

## Alias

El alias es un nombre descriptivo para reconocer la integracion dentro de ARCA. No es el CUIT, el punto de venta ni una contrasena. `arca-homologacion` es solamente un ejemplo; podes elegir `sistema-ventas-pruebas`, `mi-empresa-wsfe` u otro valor.

## Archivos generados

Para homologacion, las rutas predeterminadas son:

- `storage/certificados/homologacion/privada.key`: clave secreta. Conservala, respaldala y nunca la compartas ni la subas a ARCA o GitHub.
- `storage/certificados/homologacion/solicitud.csr`: solicitud que debes presentar en ARCA.
- `storage/configuracion/homologacion.env`: perfil privado del ambiente.
- `storage/arca-homologacion.sqlite`: operaciones del ambiente.
- `storage/instalacion.json`: progreso del asistente.

Produccion usa las mismas clases de archivo bajo rutas separadas con el nombre `produccion`.

## Pausa y reanudacion

Despues de generar el CSR, el asistente se detiene porque debes ingresar a ARCA. Podes cerrar la terminal. Para ver el avance o retomar:

~~~bash
php bin/arca instalacion:estado
php bin/arca instalacion:continuar
~~~

`install` tambien detecta una preparacion incompleta, muestra ambiente, ultimos cuatro digitos del CUIT, punto de venta, alias elegido y paso pendiente. No vuelve a generar claves.

### Recuperacion de instalaciones anteriores

Si falta `storage/instalacion.json`, `instalacion:estado` analiza `.env`, los perfiles de `storage/configuracion/`, las claves, CSR, certificados y bases SQLite existentes. Cuando puede asociarlos sin ambiguedad, crea solamente el archivo de progreso y muestra cada artefacto encontrado. No reescribe credenciales, configuracion ni SQLite.

Si dos perfiles discrepan o hay varios CSR o SQLite posibles, el proceso se detiene. Primero hay que revisar la inconsistencia; nunca elige ni regenera automaticamente.

La proteccion tambien existe dentro de los servicios que escriben archivos. `.env`, perfiles, claves, CSR y certificados usan escritura exclusiva cuando son nuevos. Un reemplazo requiere la confirmacion especifica del comando correspondiente.

## No interactivo

~~~bash
php bin/arca install --environment=homologacion --cuit=20123456786 --point-of-sale=1 --alias=mi-sistema
~~~

Si el ambiente ya existe, el comando reanuda. Para reemplazar deliberadamente configuracion y claves hacen falta `--reconfigure --yes`. Regenerar solo el par de claves requiere `certificado:generar --force --yes`. Sin esa confirmacion, no se escribe nada.

## Instalador web

Con `public/` como document root, abri directamente una URL local:

- `http://arca_facturacion.local/install/`;
- `http://arca_facturacion.test/install/`;
- `http://localhost/arca_facturacion/install/`;
- `http://127.0.0.1:8080/install/`.

No requiere HTTP Basic, token, usuario, contrasena, variables de entorno ni un comando de habilitacion. El wizard usa las mismas clases `Installer` e `InstallationStateStore` que la CLI: genera los mismos archivos, aplica las mismas validaciones y retoma el mismo progreso.

La peticion debe llegar desde loopback y usar un host local reconocido (`localhost`, loopback, `.local` o `.test`). No se confia en encabezados de proxy. Por eso el instalador no esta pensado para abrirse desde otra computadora de la red.

Se mantienen sesion estricta, CSRF, limite y validacion de uploads, escape de salida y proteccion contra sobrescritura. La clave privada nunca se muestra como contenido ni se ofrece para descargar.

Al completar la autenticacion y la simulacion, la pantalla final muestra la ruta real de `public/install/`. Eliminala o renombrala manualmente antes de publicar. No se borra automaticamente. Quitar esa carpeta deshabilita solo la web y no afecta la CLI ni ningun artefacto de la instalacion.

## Enlaces oficiales configurables

Los enlaces predeterminados apuntan a documentacion oficial de ARCA. Si ARCA mueve una pagina, podes reemplazarlos mediante las variables `ARCA_DOC_*` enumeradas en `.env.example`, sin cambiar el codigo.

## Plataformas

La base soportada es PHP CLI 8.1+ en Windows, Linux y macOS. XAMPP y Laragon deben habilitar las extensiones tanto para CLI como para el servidor web. En Apache o Nginx, el document root debe ser `public/`, nunca la raiz.
