# Auditoría de la versión anterior

Fecha de revisión: 20 de julio de 2026.

## Alcance revisado

Se revisaron todos los archivos PHP, configuración, scripts, endpoints, ejemplos, documentación y pruebas del repositorio anterior. También se revalidó el entorno local después de habilitar las dependencias requeridas.

## Entorno confirmado

- PHP 8.1.10.
- Composer 2.10.2 con acceso correcto a Packagist y GitHub.
- `ext-soap`, `openssl`, `SimpleXML`, `json`, `curl` y `zip` habilitadas.
- `pdo_sqlite` y `sqlite3` habilitadas, con SQLite 3.36.0.
- Git 2.54.0.

La conclusión provisoria anterior que proponía persistencia en archivos por falta de SQLite queda descartada. La nueva versión usa SQLite como implementación predeterminada detrás de una interfaz reemplazable.

## Qué estaba bien resuelto

- Validación del dígito verificador de CUIT.
- Firma CMS/PKCS#7 del Login Ticket Request.
- Separación de endpoints de homologación y producción.
- Caché del Ticket de Acceso con margen previo al vencimiento.
- Bloqueos de archivos alrededor de autenticación y numeración.
- Verificación TLS habilitada, timeouts y `SoapClient` con excepciones.
- Validación básica del cierre de importes y de comprobantes clase C.
- Comprobación criptográfica entre certificado y clave privada.
- Escrituras atómicas y permisos restrictivos en material sensible.
- Huella canónica para detectar el uso de una clave idempotente con datos distintos.

Estas decisiones se conservan como comportamiento, no como estructura ni API.

## Problemas encontrados

### Experiencia de uso

- El punto de entrada era una combinación de scripts y páginas sin una CLI única.
- No existían `install`, `doctor`, instalación guiada del certificado ni primera factura guiada.
- La API pública exponía arrays con códigos WSFE y obligaba a conocer nombres técnicos.
- El README comenzaba por el transporte WSAA/WSFE, no por el recorrido del desarrollador.

### Arquitectura

- `ArcaFiscalClient` concentraba autenticación, firma, caché, SOAP, mapeo y consultas.
- `InvoiceService` mezclaba validación fiscal, mapeo WSFE, numeración, locks e idempotencia.
- Las excepciones eran mayormente `RuntimeException`, sin clasificación recuperable.
- Configuración, certificados y autenticación HTTP estaban ligados al instalador web anterior.
- No había interfaces para reemplazar transporte ni persistencia.

### Idempotencia y recuperación

- La constancia se guardaba solamente después de recibir autorización.
- Un corte entre la autorización de ARCA y la escritura local podía producir un estado incierto sin registro previo.
- No había estados `pending`, `processing`, `authorized`, `rejected` y `unknown`.
- No existía reconciliación mediante `FECompConsultar`.
- La persistencia JSON por archivo no ofrecía transacciones ni consultas operativas robustas.

### Seguridad y operación

- Los endpoints HTTP devolvían mensajes de excepciones directamente.
- El instalador web seguía disponible mientras existiera un token de entorno y no tenía cierre definitivo posterior a la instalación.
- No había registro estructurado y sanitizado con `operation_id`.
- Faltaban controles explícitos contra rutas dentro de `public/` y recorrido de directorios.
- No existía una revisión automatizada de secretos ni configuración de análisis estático.

### Pruebas y distribución

- Las pruebas eran un script de aserciones, no PHPUnit.
- No había mocks de SOAP, fixtures, pruebas de repositorio SQLite ni integración opt-in.
- Faltaban CI, plantillas de GitHub, changelog, código de conducta y documentación dividida por tema.
- El nombre Composer y el namespace pertenecían al proyecto anterior.

## Decisiones de reemplazo

- Namespace público nuevo: `ArcaFacturacion`.
- Fachada `Arca` y builders expresivos; DTO tipado para uso avanzado.
- SQLite predeterminado con migraciones automáticas y `ReceiptRepository` reemplazable.
- Registro del intento antes del envío y transición transaccional de estados.
- Transporte desacoplado mediante `ArcaGateway`; implementación SOAP real y fake para tests.
- Configuración única desde variables de entorno o `.env`, cargada en un objeto tipado.
- CLI sin framework pesado; instalador web opcional reutiliza los mismos servicios.
- Errores humanos con código técnico disponible solo bajo acceso explícito.
- PHPUnit, PHPStan y GitHub Actions; integración real deshabilitada por defecto.

## Funciones eliminadas o incompatibles

- Namespace `ArcaReutilizable`.
- Endpoints públicos `api/emitir.php`, `api/estado.php` y `api/condiciones-iva.php` como API principal.
- Configuración `config/arca.json` y `ConfigStore` anterior.
- Uso directo de `InvoiceService::buildVoucher()` y arrays con nombres WSFE.
- Persistencia `ReceiptStore` basada en archivos JSON.

La migración detallada se documenta en `docs/migracion-desde-version-anterior.md`.

## Riesgos que permanecen

- ARCA puede cambiar interfaces administrativas, tablas o requisitos fiscales; los pasos se enlazan a documentación oficial y evitan depender de nombres frágiles de botones.
- Una operación `unknown` nunca debe reenviarse a ciegas: se reconcilia primero con ARCA.
- La librería no decide qué comprobante corresponde legalmente ni reemplaza asesoramiento contable.
- La validación real de homologación requiere credenciales autorizadas y se mantiene separada de la suite normal.
