# BALTO: estado técnico de pagos automáticos

**Estado:** integración opcional desarrollada y probada con HTTP simulado. Pasarela `culqi_automatico` desactivada, en modo `test`. No hay cuenta de comercio ni credenciales verificadas en esta sesión. No se realizó ningún cobro, transferencia ni llamada de prueba contra la API real de Culqi.

## Medios y alcance

La integración utiliza órdenes de Culqi y su Checkout Custom: billeteras por QR (incluido Yape/Plin según disponibilidad del proveedor), banca móvil e internet mediante PagoEfectivo y agentes. Las tarjetas y Yape por token directo están desactivados en este adaptador: requieren el flujo de cargos, distinto del de órdenes implementado aquí. No confundir QR de billetera con Yape tokenizado.

PagoEfectivo utiliza un código CIP. Una transferencia libre al CCI de BALTO no genera por sí sola una notificación que este código pueda conciliar. No se valida dinero mediante capturas, OCR ni mensajes de WhatsApp. La autorización del pago sigue siendo una acción del cliente en su banco o billetera; lo automatizado es la confirmación en la tienda.

Se conserva el retiro en tienda. Los cobros reales permanecen bloqueados mientras el catálogo sea de ejemplo o falte habilitación explícita. Los pagos por QR tienen límites propios: la documentación consultada indica S/ 6–500; por encima de ese importe el widget no ofrece la billetera QR. Los límites finales, medios habilitados, comisiones y afiliación dependen del contrato de Culqi.

## Implementación

`drupal/web/modules/custom/balto_payments/` aporta:

- Plugin nativo de pago externo de Commerce, con llave privada exclusivamente en variables del servidor.
- Total y moneda obtenidos de la entidad del pedido, con conversión decimal exacta a céntimos.
- Orden externa única por pedido, con referencia UUID. Un intento de creación interrumpido no se repite ciegamente: queda pendiente de conciliación para evitar duplicar una operación remota.
- Reserva de inventario mediante la transición nativa de registro del pedido, protegida por el control de stock existente. Un pedido con orden externa activa no puede volver a editar sus importes en el checkout.
- Notificación en `/payment/notify/culqi_automatico`: el cuerpo solo aporta el ID, y el servidor consulta el estado autenticándose contra `https://api.culqi.com/v2/orders/{id}`. Se comparan ID, referencia, entorno, importe y moneda; nunca se confía en el estado publicado por el cliente o el webhook.
- Actualización idempotente del pago y del saldo pagado de Commerce; los duplicados no descuentan stock dos veces.
- Vencimiento o eliminación verificados: anulación del pago pendiente, cancelación del pedido y devolución de la reserva.
- Bloqueos de pago y pedido para serializar notificaciones. Los errores del proveedor devuelven 503 para permitir reintentos, sin exponer cuerpos o claves.
- Reconciliación de pendientes en Drupal cron y en `scripts/reconcile-automatic-payments.sh`. El webhook es el camino inmediato; el reconciliador recupera notificaciones perdidas. Los lotes rotan por fecha de cambio.

La confirmación privada del pedido consulta periódicamente su propia página para reflejar la confirmación o el vencimiento sin que el cliente actualice manualmente; conserva un enlace de actualización como alternativa.

Los pagos recibidos después de una cancelación se registran como incidencia para conciliación, sin reactivar el pedido automáticamente. No se implementaron reembolsos automáticos ni notificaciones de correo/SMS. Los envíos de recibos existentes continúan sin SMTP.

## Pendiente antes de una prueba de integración real

Falta confirmar el proveedor elegido y su cuenta de comercio; obtener llaves públicas/privadas **de prueba** del mismo entorno; preparar una URL HTTPS pública que reciba los webhooks; y habilitar el evento `order.status.changed` en Culqi. `localhost` no recibe notificaciones externas sin un entorno de pruebas accesible.

Las variables están declaradas en `.env.example` y en el bloque de entorno privado de Drupal de Compose: `BALTO_CULQI_PUBLIC_KEY`, `BALTO_CULQI_SECRET_KEY`, `BALTO_CULQI_ALLOW_LIVE=false`. Las llaves no se guardan en configuración exportable ni en Git. Después de configurarlas se debe recrear el contenedor Drupal para actualizar su entorno, y recién entonces habilitar la pasarela en administración. Las pruebas reales de QR y CIP, cierre del widget, notificaciones, vencimiento y reintentos aún deben hacerse con el proveedor.

Para producción se necesitan además inventario y precios reales, HTTPS, credenciales `live`, habilitación explícita y la conciliación programada. Los archivos de `deploy/systemd/balto-payments.*` son plantillas revisables para ejecutar el reconciliador cada minuto; no se instalaron ni activaron en AWS. La ruta de trabajo se ajusta mediante `BALTO_PROJECT_DIR` al instalarlas. Que el servidor de AWS esté apagado impide que reciba notificaciones y ejecute conciliación.

## Referencias verificadas

- Culqi órdenes: https://docs.culqi.com/es/documentacion/pagos-online/ordenes-de-pago/ordenes/
- Billeteras y confirmación por evento: https://docs.culqi.com/es/documentacion/pagos-online/ordenes-de-pago/billetera-moviles/
- PagoEfectivo: https://docs.culqi.com/es/documentacion/pagos-online/ordenes-de-pago/pago-efectivo/
- Checkout Custom actual: https://docs.culqi.com/es/documentacion/checkout/checkout-custom/
- Webhooks: https://docs.culqi.com/es/documentacion/pagos-online/webhooks/
- SDK oficial PHP revisado como referencia: https://github.com/culqi/culqi-php
- Alternativa revisada: Mercado Pago documenta Yape en Checkout API: https://www.mercadopago.com.pe/developers/es/docs/checkout-api-orders/payment-integration/websites/yape

Se revisó `commerce_culqi` 3.0.0 compatible con Drupal 11/Commerce 3.3. No se incorporó: su manejador de eventos examinado asigna el estado recibido del evento sin la consulta adicional de integridad que se requiere aquí. La integración local está separada del SDK y utiliza el cliente HTTP ya incluido por Drupal, evitando dependencias nuevas.

## Verificación local

`tests/automatic-payments.php`, ejecutado con Drush, usa entidades y transacciones reales de Commerce junto con Guzzle MockHandler. Cubre 40 comprobaciones: entornos, bloqueo de producción, importes, orden única, reserva, formulario sin clave privada, ID desconocido, pago pendiente, integridad, webhook falso/malformado, caída del proveedor, confirmación, duplicados, expiración, cancelación y restauración de inventario. Las respuestas HTTP son simuladas: no equivalen a homologación con Culqi. Los datos de prueba se revierten mediante transacción.
