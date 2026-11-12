# Fase 3: catálogo y existencias

## Explorar la tienda

1. Abre <http://localhost:8080/tienda> o pulsa **Tienda** en la cabecera.
2. Escribe un nombre o SKU en **Buscar producto**.
3. Selecciona una categoría y, si lo necesitas, una especie.
4. Pulsa **Aplicar filtros**.
5. Abre **Ver producto** para consultar descripción, precio, imagen y existencias.
6. Usa la paginación para recorrer los quince artículos.

**Resultado esperado:** los filtros se combinan y quedan en la URL. Las páginas muestran como máximo doce productos. Una búsqueda sin resultados ofrece limpiar filtros o consultar por WhatsApp.

Todos los productos, precios y cantidades iniciales son **de ejemplo**. Las imágenes son ilustraciones referenciales locales, sin logotipos de fabricantes. La advertencia aparece en catálogo y detalle. No representa el inventario real de BALTO.

## Probar existencias

1. Busca **Dog Chow** y abre su detalle.
2. Comprueba su precio de ejemplo de **S/ 189.90** y sus **12 unidades** iniciales.
3. Intenta agregar trece unidades.
4. Agrega una unidad y abre **Carrito**.
5. Regresa al producto e intenta agregar doce unidades más.
6. Busca **Ricocat Gatitos** para ver un producto agotado.

**Resultado esperado:** se rechazan las trece unidades y también el exceso acumulado con lo que ya está en el carrito. Un producto agotado conserva su detalle, pero su botón está deshabilitado y el servidor rechaza un envío manipulado.

Agregar al carrito no reserva ni descuenta existencias. La configuración del módulo deja el descuento para el evento de pedido registrado; la compra y el pago simulado se completarán y comprobarán en Fase 4.

## Editar un producto y su precio

1. Inicia sesión como administrador con las credenciales de `.env`.
2. Abre <http://localhost:8080/admin/commerce/products>.
3. Edita un producto de tipo **Producto BALTO**.
4. Cambia nombre, descripción, categoría, especie o imagen según corresponda.
5. Cambia el precio en **Información del producto**.
6. Pulsa **Guardar** y abre el catálogo en una ventana privada.

**Resultado esperado:** el detalle y las tarjetas muestran el precio nuevo sin editar Twig ni ejecutar scripts. Los cambios invalidan la caché correspondiente.

## Ajustar las existencias

1. Abre la edición de un producto desde el panel.
2. Busca **Existencias (movimientos)** dentro de la información del producto.
3. Consulta **Existencias actuales**.
4. Escribe un ajuste positivo para sumar o negativo para restar unidades; por ejemplo, `2` suma dos unidades.
5. Guarda.
6. Revisa el saldo en el detalle público.

**Resultado esperado:** Commerce Stock registra el movimiento y el sitio muestra el nuevo saldo. El campo es un **ajuste**, no la cantidad absoluta que quieres dejar. No vuelvas a introducir el saldo completo como si fuese un ajuste.

La semilla se ejecuta una sola vez y los reinicios no reponen el inventario ni restauran los precios. Los archivos JSON y las ilustraciones sirven únicamente para la carga inicial; el sitio consulta Drupal/MariaDB en cada operación.

## Reemplazar una imagen de ejemplo

1. Edita el producto desde Commerce.
2. Reemplaza la imagen del campo **Imagen del producto** por un PNG, JPG, JPEG o WebP de hasta 2 MB.
3. Escribe un texto alternativo descriptivo.
4. Guarda y comprueba el detalle.

**Resultado esperado:** la imagen subida queda como archivo permanente de Drupal en el volumen persistente. Los PNG de muestra ya están incluidos en el repositorio. Para regenerar esas ilustraciones en desarrollo se usa `python3 scripts/generate-product-placeholders.py`, que necesita la herramienta `rsvg-convert`; no hace falta para levantar la demo.

## Decisiones técnicas

- **Commerce Stock 3.0.0:** su [versión estable declara compatibilidad con Drupal 11](https://www.drupal.org/project/commerce_stock/releases/3.0.0). Se verificaron las APIs en el código instalado. No se instaló otro módulo ni se inventó un sustituto.
- **Un almacén:** `Almacén central BALTO`, para la única tienda de la demo. El saldo procede del registro de movimientos de Commerce Stock, no del valor inicial de un campo ni de un contador en JavaScript.
- **Validación:** `commerce_stock_enforcement` comprueba los formularios de carrito y lo ya agregado. Además, un comprobador registrado en la API de disponibilidad de Commerce valida cantidades contra el mismo saldo en servidor.
- **Productos y variaciones:** la información descriptiva e imagen pertenecen a `vet_product`; SKU, precio y stock pertenecen a su única variación `vet_variation`. Es el [modelo de productos documentado por Commerce](https://docs.drupalcommerce.org/v2/developer-guide/products/product-management/).
- **Filtros GET:** consulta de entidades de Drupal con validación de entrada, acceso a productos publicados y escape de salida con Twig. No requieren JavaScript ni exponen información de clientes.
- **Caché:** las páginas incluyen etiquetas de productos y variaciones. Los movimientos de Commerce Stock invalidan la variación, de modo que también actualizan las tarjetas y el detalle.
- **Ilustraciones:** SVG originales convertidos a PNG para usar el campo de imagen estándar de Drupal. Los SVG fuente no se aceptan como archivos subidos desde el panel.

## Verificación

**Resultado del navegador:** 49 comprobaciones correctas, sin errores JavaScript ni respuestas locales HTTP 500. Se comprobó que la protección de agotados permanece al manipular el HTML, que se rechazan cantidades excesivas y que editar precio o stock desde Commerce actualiza detalle y catálogo públicos. Los valores usados en las pruebas se restauraron.

La instalación completa también pasó en una base vacía aislada (`balto-phase3-fresh`), sin advertencias ni errores en su registro. Repetir el instalador conservó el precio, el saldo de stock y los quince productos existentes. El [informe de integración](verificacion-integracion-fase-3.json) registra estas comprobaciones.

```bash
bash scripts/verify.sh
deno run --node-modules-dir=none --allow-all tests/phase3-browser.mjs
```

Si usas el motor Rootless de esta computadora, primero prepara la terminal según [Docker local](docker-local.md). En otra computadora, define `BALTO_BROWSER` con la ruta de un navegador Chromium compatible para las pruebas de desarrollo.

El navegador comprueba cinco anchos de pantalla, imágenes, paginación, búsquedas, filtros, entradas inválidas, protección de productos agotados y cantidades, carrito real y edición administrativa. Restaura el precio y el stock usados en la prueba. El informe se guarda en `docs/verificacion-fase-3.json` y las capturas en `docs/capturas/fase-3-*.png`.

El comprobador PHP verifica los quince productos con imágenes permanentes, moneda PEN, categorías, especies, dos agotados y el contrato de disponibilidad de Commerce. El checkout, los roles de tienda y la compra completa corresponden a Fase 4.
