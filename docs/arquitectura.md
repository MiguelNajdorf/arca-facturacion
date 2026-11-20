# Arquitectura

## Principio

La API y el instalador parten del recorrido de quien integra por primera vez. SOAP, XML, firma, tickets y codigos WSFE quedan detras de una fachada pequena. El rediseño pedagogico no modifica el nucleo fiscal.

## Capas

1. **API publica y dominio**: `Arca`, builders, `Invoice`, validaciones e `InvoiceResult`.
2. **Aplicacion**: emision, simulacion, idempotencia, recuperacion y diagnostico.
3. **Infraestructura ARCA**: WSAA, WSFE, firma CMS, mapeo y parsing.
4. **Persistencia fiscal**: contrato `ReceiptRepository` e implementacion SQLite.
5. **Instalacion**: CLI y web sobre `Installer`, `InstallationStateStore` e `InstallationGuide`.

CLI y web comparten el mismo estado, las mismas explicaciones y los mismos hitos. Ninguna interfaz puede marcar una autorizacion como completa sin comprobarla online.

## Flujo de emision

1. Validar la factura sin red.
2. Crear el intento idempotente `pending`.
3. Tomar un lock fiscal por CUIT, ambiente, punto de venta y tipo.
4. Consultar el ultimo numero autorizado.
5. Reservar el siguiente numero y pasar a `processing`.
6. Enviar una unica vez a ARCA.
7. Guardar `authorized` o `rejected`.
8. Ante una respuesta ambigua, guardar `unknown`.
9. Un reintento con igual clave devuelve la operacion existente.
10. `recuperar()` usa `FECompConsultar` para reconciliar estados inciertos.

SQLite usa WAL, claves unicas y transacciones. Cada ambiente tiene su propia base. `ReceiptRepository` permite sustituir SQLite por una implementacion compartida.

## Configuracion y ambientes

La fuente de verdad en ejecucion es el entorno. Para instalaciones simples, `.env` materializa el perfil activo. Los perfiles privados se conservan en `storage/configuracion/homologacion.env` y `storage/configuracion/produccion.env`.

`ambiente:cambiar` activa un perfil copiandolo a `.env`. No mueve ni reemplaza credenciales.

Las rutas quedan separadas:

- certificados: `storage/certificados/<ambiente>/`;
- bases: `storage/arca-<ambiente>.sqlite`;
- tickets: `storage/tickets/<ambiente>/`;
- locks: `storage/locks/<ambiente>/`;
- logs: `storage/logs/<ambiente>/`.

## Estado del instalador

`storage/instalacion.json` registra alias, CUIT, punto de venta, rutas e hitos de cada ambiente. La presencia de claves, CSR y certificado se vuelve a comprobar al leer el estado.

Los hitos administrativos no se fingen: `doctor --online` debe autenticar y consultar ARCA antes de marcar autorizacion y conexion como verificadas. Simulacion y primera emision avanzan cuando sus comandos terminan correctamente.

## Certificados y tickets

- El generador nunca sobrescribe una clave sin `--force` y una segunda confirmacion.
- El instalador valida formato, vigencia y coincidencia criptografica.
- Los tickets se separan por ambiente, CUIT y servicio, se renuevan con margen y lock exclusivo.
- Claves, certificados, CSR, tickets, bases, logs y estado viven fuera de `public/`.

## Errores y datos tecnicos

Las excepciones exponen un mensaje humano y un codigo tecnico opcional. XML, tokens, firmas y claves no forman parte de mensajes HTTP ni logs. La respuesta cruda solo aparece en modo avanzado explicito.

## Dependencias

CodeQL se evaluo y se descarto porque GitHub no ofrece analizador para PHP. La calidad automatizada usa PHPUnit, PHPStan, el escaneo local de secretos y acciones de CI fijadas a hashes completos.

- Runtime: `soap`, `openssl`, `pdo_sqlite`, `sqlite3`, `SimpleXML` y `json`.
- Desarrollo: PHPUnit y PHPStan.
- No se incorpora framework de consola, ORM ni logger externo.
