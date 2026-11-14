# Auditoría de color del sitio local BALTO

Revisé en navegador la portada, la tienda, una búsqueda de productos y el carrito, todos con respuesta HTTP 200. Los bloques de servicios, sedes, historias y contacto están en la portada, así que también se recorrieron en su página completa.

## Ajustes realizados

- Título de portada: acento dorado oscuro `#8a7000` sobre crema `#f4f1e8`, contraste calculado 4.23:1. Es texto grande, para el que WCAG 2.2 AA pide 3:1.
- Pie de página: texto secundario `#53614f` sobre `#fffdf9`, contraste 6.3:1.
- Números de sedes: `#596653` sobre blanco, contraste 6.1:1.
- Tienda y carrito: no aparecieron textos bajo 4.5:1 en esta revisión automática.

Los tres puntos del logo se consideran parte de la marca; los puntos amarillos junto al horario son decorativos. El script mide texto visible contra el fondo CSS más cercano; no es una auditoría de accesibilidad completa ni evalúa texto incrustado dentro de fotografías. El título de portada usa 80 px en escritorio y clasifica como texto grande.

## Lectura de la paleta

La paleta mantiene verde profundo para marca y acciones, crema para descanso visual y mostaza para acentos. La revisión sistemática de Jonauskaite y Mohr encuentra asociaciones frecuentes de verdes con emociones positivas de baja activación y de amarillos con emociones positivas más activas; los autores recalcan que son asociaciones, no respuestas universales. La evidencia apoya la jerarquía usada, no demuestra una preferencia estética específica de los clientes de BALTO.

Referencias: [Jonauskaite y Mohr, revisión sistemática (2025)](https://link.springer.com/article/10.3758/s13423-024-02615-z); [W3C, WCAG 2.2, contraste mínimo](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum).
