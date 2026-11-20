# Preguntas frecuentes

## El proyecto elimina todos los pasos en ARCA?

No. Automatiza la parte tecnica. El titular debe gestionar certificado, autorizacion, ambiente y punto de venta dentro de ARCA.

## Puedo probar sin emitir?

Si. simular() y factura:simular validan y construyen el pedido sin usar la red.

## Puedo usar MySQL o PostgreSQL?

Si. Implementa ReceiptRepository e inyectalo mediante Arca::crear().

## Que hago con una operacion unknown?

Ejecuta operaciones:recuperar ID. No crees otra idempotency key ni reenvies hasta reconciliar.

## Guarda la clave en SQLite?

No. SQLite almacena datos operativos resumidos, nunca claves, certificados, tickets o firmas.

## Genera el PDF o QR?

No. Devuelve los datos autorizados para que tu sistema genere la representacion que corresponda.

## Es software oficial?

No. Es un proyecto independiente, gratuito y abierto.
