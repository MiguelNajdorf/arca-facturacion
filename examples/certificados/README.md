# Certificados de ejemplo

`cert.example.txt` y `key.example.txt` explican el formato esperado, pero no contienen bloques PEM. Esto evita que alguien reemplace accidentalmente un archivo versionado por una credencial real.

- `key`: clave privada creada localmente al generar el CSR. Nunca se entrega a ARCA ni se publica.
- `cert`: certificado X.509 que ARCA emite a partir del CSR.
- Ambos archivos deben corresponder al mismo par criptografico.
- El certificado de homologacion no sirve en produccion, y viceversa.

No guardes credenciales reales dentro de `examples/`. Instalalas con `php bin/arca certificado:instalar RUTA`; el comando las valida y las guarda bajo `storage/certificados/<ambiente>/`, fuera del document root.
