# Seguridad

- Publica solamente public/, nunca la raiz.
- Mantiene .env, storage/, certificados, claves, tickets y SQLite fuera del document root.
- Usa permisos 0600 para secretos y la base cuando el sistema lo soporte.
- No desactiva verify_peer ni verify_peer_name.
- Los logs sanitizan campos de token, firma, clave, certificado y contrasena.
- Debug esta deshabilitado por defecto y la respuesta cruda requiere habilitacion explicita.
- El instalador web exige simultaneamente una conexion desde loopback y un host local reconocido; no confia en encabezados de proxy.
- No usa login, token ni contrasena: su superficie se elimina manualmente quitando `public/install/` al finalizar.
- Mantiene sesion estricta, CSRF, escape de salida y una politica CSP restrictiva.
- Las cargas de certificado se limitan a 64 KB y se validan antes de instalar.
- Ningun flujo sobrescribe configuracion o claves existentes sin confirmacion explicita; el instalador web rechaza la sobrescritura.
- Eliminar `public/install/` no elimina ni modifica configuracion, claves, certificados, SQLite o la CLI.
- Homologacion y produccion tienen claves, certificados, bases, tickets, locks y logs separados.
- Las rutas sensibles se rechazan dentro de public/ y se normalizan para impedir traversal.
- Las pruebas de homologacion son opt-in y la suite normal no usa credenciales.
- Las acciones de CI estan fijadas a hashes completos y Dependabot propone sus actualizaciones para revision.
- El repositorio incluye configuracion de Gitleaks; ejecutala tambien sobre el historial una vez publicado.

## Incidente

Si una clave privada, certificado, ticket o .env se publica, revoca y rota la credencial. Borrarla del ultimo commit no la elimina del historial.

Consulta tambien SECURITY.md para reportar vulnerabilidades.
