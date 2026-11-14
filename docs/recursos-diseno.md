# Recursos de diseño utilizados en BALTO

Se mantienen Drupal Commerce, las fotografías existentes y la identidad verde/amarilla. Las habilidades se instalaron exclusivamente en `.agents/skills/` del proyecto y estarán disponibles para descubrimiento automático en el siguiente turno de Codex. Para este trabajo se leyeron y aplicaron sus instrucciones directamente.

| Recurso | Fuente | Aplicación |
| --- | --- | --- |
| Impeccable | https://github.com/pbakaus/impeccable | Revisión final, jerarquía, foco, adaptación y detección de problemas. |
| UI UX Pro Max | https://github.com/nextlevelbuilder/ui-ux-pro-max-skill | Búsqueda verificada `friendly rounded pet`: resultado Soft Rounded, Varela Round + Nunito Sans. Revisión de foco visible. |
| Frontend Design | https://github.com/anthropics/skills/tree/main/skills/frontend-design | Composición, tipografía y eliminación de etiquetas y números decorativos. |
| Interface Design | https://github.com/Dammyjay93/interface-design | Consistencia de filtros, controles, precios y panel de compra. Se aplica al flujo de compra, no como guía de marketing. |
| Lucide | https://github.com/lucide-icons/lucide | 21 iconos SVG compartidos mediante la plantilla central. Licencia ISC/MIT conservada en `templates/icons/LICENSE.txt`. |
| Design Resources for Developers | https://github.com/bradtraversy/design-resources-for-developers | Directorio de referencia; su sección Fonts enlaza Google Fonts, utilizado para obtener las fuentes. No se instala como skill. |

## Dirección aplicada

Familias con mascotas que consultan atención veterinaria y compran productos desde el celular. El contenido clínico y los precios deben leerse con claridad; las llamadas a cita y compra marcan la jerarquía. El gatito, las historias reales y las fotos del equipo identifican a BALTO.

Paleta existente: verde `#1f4d2b`, verde profundo `#153d23`, crema `#f8f7f0`, amarillo `#f2c94c`, texto `#202b23`, bordes `#dfe5dc`. Se mantienen superficies planas y separaciones suaves. Los campos comparten fondo `#f8faf6`, borde `#abbba4` y foco `#916600`. Espaciado de controles y compra en múltiplos de 4 píxeles, campos de al menos 48 píxeles de altura.

- Títulos: Varela Round, peso 400. Cuerpo y controles: Nunito Sans, pesos 400/600/700/800.
- Las fuentes se sirven localmente desde `fonts/`, sin llamadas a Google Fonts en el navegador; ambas conservan sus licencias OFL.
- Los iconos Lucide se incluyen desde archivos Twig locales, con `aria-hidden` para los símbolos decorativos y nombres en los botones que los contienen. El símbolo existente de WhatsApp se conserva.
- La ficha de producto agrupa cantidad y compra sobre una superficie crema y destaca precio y disponibilidad. Los números usan cifras tabulares.
- Los filtros conservan etiquetas visibles y controles HTML nativos. No se añadieron dependencias React ni se reemplazó Drupal.

## Verificación

Revisión de portada y catálogo a 360, 390, 768, 1024 y 1440 píxeles, con imágenes y fuentes locales, navegación de carrusel y categorías. Las comprobaciones existentes de catálogo validan además precios, stock y carrito. La prueba de chat se actualizó para esperar la respuesta de horarios sin depender de la antigua cadena fija `7:00`; la hospitalización de 24 horas continúa verificada.

## Referencias de tienda aplicadas

Se inspeccionaron los ejemplos de colecciones y carrito de HyperUI (`public/examples/marketing/product-collections/1.html` y `carts/1.html`) y la estructura de las tiendas Medusa DTC Starter, Vercel Shop y Saleor Storefront. Estos últimos son alternativas basadas en Next.js y sus propios servicios de comercio: se utilizaron como referencia de recorrido y jerarquía, no se instalaron sobre Drupal. Flowbite y daisyUI son referencias de controles y consistencia; no se añadieron sus frameworks completos.

La portada incorpora buscador de productos, navegación por perro/gato y cuatro productos obtenidos del catálogo real de Commerce. Las tarjetas reutilizan `product-card.html.twig`; la portada invalida su caché al cambiar productos o variaciones. El resumen de checkout conserva el foco en el pedido. No se copiaron imágenes ni marcas de las tiendas de ejemplo.

Fuentes: https://github.com/markmead/hyperui, https://github.com/medusajs/dtc-starter, https://github.com/vercel/shop, https://github.com/saleor/storefront, https://github.com/themesberg/flowbite y https://github.com/saadeghi/daisyui.
