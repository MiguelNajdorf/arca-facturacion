# Persistencia e idempotencia

La implementacion predeterminada usa una base SQLite separada por ambiente:

- `storage/arca-homologacion.sqlite`;
- `storage/arca-produccion.sqlite`.

- WAL y synchronous=FULL;
- claves unicas para operation_id e idempotency_key;
- estados pending, processing, authorized, rejected y unknown;
- request y respuesta resumidos, errores y observaciones;
- sin claves, certificados, tickets ni secretos.

ReceiptRepository es el contrato reemplazable. Una aplicacion con varias instancias puede implementar el mismo contrato sobre MySQL o PostgreSQL y sustituir SQLite al construir Arca::crear().

El progreso pedagogico del instalador se guarda aparte en `storage/instalacion.json`. No contiene claves ni certificados. Los perfiles privados se conservan bajo `storage/configuracion/`; `.env` representa solamente el ambiente activo.

## Garantia practica

El intento se persiste antes de enviar. Una misma clave con el mismo payload devuelve el resultado anterior; con payload diferente lanza DuplicateInvoiceException.

Si la red falla despues de reservar el numero, el estado pasa a unknown. La recuperacion consulta FECompConsultar. La libreria no reenvia automaticamente un estado incierto.

Respalda la base con un mecanismo compatible con WAL o usa la API de backup de SQLite. No copies solo el archivo principal mientras hay escrituras activas.
