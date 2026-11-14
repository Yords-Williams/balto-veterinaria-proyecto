# Prueba de Taste y Emil en el catálogo de BALTO

Tipo: tienda física con catálogo Commerce. Alcance: `/tienda`, sin sustituir Drupal ni los flujos de pedidos.

Skills instaladas en `.agents/skills/`: `taste-skill` (Leonxlnx/taste-skill) y `emil-design-eng` (emilkowalski/skills). Los archivos quedan disponibles para las siguientes sesiones.

Taste aporta composición con fotografía, fondo salvia y búsqueda destacada. Emil aporta respuesta breve al pulsar, hover solo con puntero preciso y ausencia de transiciones con movimiento reducido. Los filtros usan `details` nativo para mantener teclado y funcionamiento sin JavaScript; el script los pliega inicialmente en pantallas menores de 901 px.

| Before | After | Why |
| --- | --- | --- |
| Búsqueda dentro de la barra lateral | Búsqueda en la portada con etiqueta visible | Encontrar productos desde el primer bloque |
| Filtros ocupando espacio en móvil | Panel plegable con teclado | Llegar antes a los resultados |
| Tarjetas abiertas | Tarjetas con imagen, precio y acción delimitados | Facilitar la lectura del catálogo |
| Hover heredado también en táctil | Hover con puntero preciso; pulsación breve | Evitar estados persistentes al tocar |

Adaptación deliberada: se conservan datos de contacto reales, navegación entre páginas, aviso de demostración y las ilustraciones de productos existentes. Las reglas del kit para crear una landing nueva (secciones de una pantalla, contactos `.example`, fotografías numerosas y momentos decorativos) no se aplican a este catálogo operativo. No se inventan reseñas, productos, entregas ni pagos confirmados. Voz: cercana y concreta; sin nuevos precios u horarios supuestos.

Verificación: `deno run -A tests/taste-emil-browser.mjs`; 20 comprobaciones de contenido, imágenes, anchura, teclado, búsqueda y movimiento reducido en 360, 390, 768, 1024 y 1440 px. Resultados en `verificacion-taste-emil.json`. Capturas de escritorio y móvil en `capturas/taste-emil-tienda*.png`. Portada principal responde HTTP 200; JavaScript pasa `node --check`.

El generador de capturas de Taste se ejecutó correctamente. Su gate informó `GATE: OFF (no build active)`; no se presenta esta adaptación como certificada por el kit. La comprobación funcional del navegador es independiente de ese gate.

El checker `consistency.mjs` terminó con 69 FAIL y 87 WARN antes de los últimos ajustes. Incluye reglas de landing incompatibles con el alcance (secciones de pantalla completa y teléfonos ficticios), además de observaciones heredadas de cabecera/pie. Se corrigieron el salto de nivel de título del bloque de ayuda, el contraste del texto nuevo y el foco recortado del panel. No equivale a una auditoría completa de accesibilidad aprobada.
