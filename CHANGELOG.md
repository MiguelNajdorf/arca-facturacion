# Changelog

Todos los cambios notables se documentan aqui. El proyecto sigue versionado semantico.

## [Unreleased]

## [0.1.0] - 2026-11-22

Primera version publica.

### Added

- CLI guiada, doctor e instalador web local protegido.
- Certificados, API fluida, WSAA y WSFE encapsulados.
- SQLite mediante ReceiptRepository reemplazable.
- Idempotencia y recuperacion de operaciones inciertas.
- PHPUnit, PHPStan, CI, ejemplos y documentacion.
- Asistente pedagogico CLI/web con progreso persistente y reanudacion.
- Perfiles, certificados, bases, tickets y logs separados por ambiente.
- Comandos instalacion:estado, instalacion:continuar, ambiente:cambiar y produccion:preparar.
- Recuperacion segura de instalaciones sin `instalacion.json`, con inventario de artefactos y detencion ante ambiguedades.
- Guardas fisicas contra reemplazo accidental de `.env`, perfiles, claves privadas, CSR, certificados y SQLite.
- `produccion:preparar` conserva el `.env` activo; produccion se activa explicitamente con `ambiente:cambiar produccion`.
- Wizard web local sin login ni token, con reanudacion cruzada CLI-web y cierre seguro mediante eliminacion manual de `public/install/`.
