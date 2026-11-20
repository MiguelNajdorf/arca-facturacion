# Primeros pasos

1. Ejecuta `composer install`.
2. Elegi una interfaz: `php bin/arca install` o abri `http://arca_facturacion.local/install/`.
3. Elegi homologacion si es tu primera integracion.
4. Guarda la clave privada y presenta solamente el CSR en WSASS.
5. Cerra la terminal si lo necesitas; el progreso queda guardado.
6. Descarga el certificado y ejecuta `php bin/arca certificado:instalar RUTA`.
7. Autoriza WSFE siguiendo la guia que muestra el comando.
8. Ejecuta `php bin/arca doctor --online`.
9. Simula con `php bin/arca factura:simular --total=15000`.
10. Ejecuta `php bin/arca instalacion:continuar` para ver la primera emision controlada.

Comandos para no perderte:

~~~bash
php bin/arca instalacion:estado
php bin/arca instalacion:continuar
~~~

Para produccion usa `php bin/arca produccion:preparar`. No edites solo la variable de ambiente.

Antes de emitir desde tu aplicacion, lee los ejemplos y elegi una clave de idempotencia estable por venta.

La CLI y el instalador web comparten `storage/instalacion.json`, los perfiles y todos los artefactos. Podes cambiar de interfaz en cualquier momento sin reiniciar. Cuando la instalacion web finalice, elimina manualmente `public/install/`; esto no afecta la CLI.
