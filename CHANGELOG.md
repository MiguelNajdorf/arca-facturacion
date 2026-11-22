# Changelog

Todos los cambios notables se documentan aqui. El proyecto sigue versionado semantico.

## [Unreleased]

## [0.3.0] - 2026-11-22

### Added

- `$arca->pdf()`: factura A4 con letra y codigo, emisor, receptor, items, totales, CAE y el QR obligatorio de ARCA.
- `$arca->urlQr()`: texto del QR segun la especificacion oficial de ARCA (version 1).
- `doctor` revisa las extensiones `gd`, `mbstring` y `zlib` que usa el PDF.
- Dependencias `chillerlan/php-qrcode` y `setasign/fpdf` (MIT). Facturar sigue funcionando sin Composer; el PDF lo necesita.

## [0.2.0] - 2026-11-22

### Added

- `iva(alicuota, base, importe)` en el builder: todas las alicuotas de WSFE (0, 2.5, 5, 10.5, 21 y 27) y varias por comprobante.
- Validacion local de que la suma de las bases de IVA sea igual al neto gravado.

### Fixed

- Llamar mas de una vez a `iva21()` reemplazaba el IVA total en lugar de sumarlo.
- El CUIT de ejemplo `30712345678` de la documentacion tenia el digito verificador incorrecto; ahora es `30712345671`.

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
