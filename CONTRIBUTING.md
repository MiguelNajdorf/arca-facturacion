# Contribuir

Gracias por mejorar arca_facturacion.

~~~bash
composer install
composer check
~~~

Usa PHP 8.1 o superior con soap, openssl, SimpleXML, pdo_sqlite y sqlite3.

1. Mantiene el cambio enfocado.
2. Agrega pruebas para comportamiento nuevo o corregido.
3. No cambia reglas fiscales sin fuente oficial y caso de prueba.
4. Indica si se valido con mocks, localmente o en homologacion.
5. Actualiza documentacion y CHANGELOG cuando corresponda.

Nunca agregues CUIT reales de terceros, certificados, claves, CSR, tickets, bases SQLite, .env ni respuestas productivas sin anonimizar.
