# API de consulta para n8n

La API permite consultar información pública del negocio almacenada en Drupal y MariaDB. Lee precios de Commerce, saldos del libro de Commerce Stock, categorías de taxonomía, nodos de servicios y configuración del negocio. No permite escribir ni consultar compradores o pedidos.

## Consultar desde otra aplicación

Requisito: servicios iniciados y acceso al archivo `.env` local. Para quien administra la integración:

1. Consulta `VET_API_KEY` en `.env` sin publicarla ni incluirla en capturas.
2. Configura una credencial de cabecera HTTP llamada `X-API-KEY` en la aplicación que consulta.
3. Introduce la clave como valor de esa credencial.
4. Envía una consulta GET a una de las rutas de la tabla.

Resultado esperado: respuesta JSON con `data`. Desde la red de Compose, n8n utilizará `http://drupal/api/vet/...`; desde esta computadora se utiliza `http://localhost:8080/api/vet/...`. La configuración concreta del workflow corresponde a la fase 6.

La clave solo debe viajar entre servidores. El widget del navegador consultará n8n; no contendrá esta clave. En AWS se deberá usar HTTPS para el tráfico público; la API no habilita CORS para navegadores externos.

## Rutas y filtros

| Consulta GET | Resultado |
| --- | --- |
| `/api/vet/products?q=&category=&species=&page=0` | Variaciones publicadas con nombre, SKU, precio, moneda, saldo, disponibilidad, categoría, especies y enlace |
| `/api/vet/products/BAL-ALI-001` | Producto identificado por su SKU exacto |
| `/api/vet/categories` | Identificadores y nombres de categorías |
| `/api/vet/services` | Servicios publicados, descripción, ícono y enlace |
| `/api/vet/schedule` | Horarios, marca de ejemplo, hospitalización, sedes y contacto |

`q` busca una parte del nombre o SKU y admite hasta 100 caracteres. `category` usa el identificador devuelto por categorías; `species` admite `perro`, `gato` u `otros`. Los filtros se combinan. `page` comienza en cero; cada página contiene hasta 50 resultados. La respuesta de listado incluye `meta.total`, `meta.page`, `meta.page_size` y `meta.catalog_is_example`. Un buscador sin coincidencias devuelve `200` con `data: []`.

Ejemplo de estructura de un producto; los importes y existencias se consultan de nuevo en cada petición:

```json
{
  "data": {
    "sku": "BAL-ALI-001",
    "name": "Dog Chow Adulto 15 kg",
    "price": "S/ 189.90",
    "price_number": "189.900000",
    "currency": "PEN",
    "stock": 12,
    "available": true,
    "category_id": "1",
    "category": "Alimentos",
    "species": {"perro": "Perro"},
    "url": "/product/1"
  }
}
```

Este ejemplo ilustra campos, no un inventario garantizado. La respuesta también incluye identificador, descripción y fotografía referencial. Los enlaces de productos son relativos a Drupal; n8n usará la URL pública del sitio al responder al usuario. Los servicios leen sus datos de contenido publicado; los horarios conservan `schedule_is_example` para que el chatbot distinga un horario pendiente de confirmar.

## Entender errores

| Código | Significado | Acción |
| --- | --- | --- |
| 200 | Consulta correcta | Usa `data`; puede ser una lista vacía |
| 400 | Filtro o estructura inválidos | Revisa longitud, identificador, especie y página |
| 401 | Cabecera ausente o clave incorrecta | Revisa la credencial del servidor |
| 404 | SKU o ruta inexistentes | Sugiere opciones disponibles |
| 405 | Método distinto del permitido | Usa GET |
| 503 | Clave del servidor sin configurar | Revisa `.env` y recrea Drupal |
| 500 | Error interno | Revisa los logs; no se publican trazas ni consultas SQL |

Los errores tienen forma `{"error":{"code":"...","message":"..."}}`. La autenticación se realiza antes del enrutamiento: una clave inválida devuelve 401 incluso al consultar una ruta inexistente. El servidor usa comparación de clave en tiempo constante y exige al menos 32 caracteres de configuración. El generador crea claves aleatorias de 64 caracteres.

Todas las respuestas de API indican `Cache-Control: no-store, private`; las rutas no admiten caché de página. Se seleccionan explícitamente los campos públicos en vez de serializar entidades completas. Los productos o servicios sin publicar quedan fuera de la API. La clave no concede permisos de edición ni acceso administrativo.

## Ejecutar las pruebas

Desde la carpeta del repositorio, con Python 3 y Docker disponibles:

```bash
python3 tests/phase5-api.py
```

**Aviso previo:** la prueba cambia temporalmente el precio de Dog Chow a S/ 190.50 y su estado de publicación; los restaura en un bloque de limpieza aunque falle la consulta. Usa una demo de desarrollo sin ediciones simultáneas de ese producto.

Resultado comprobado: **123 comprobaciones HTTP correctas**, registradas en el [informe de verificación](verificacion-fase-5.json). También pasó la verificación de integración de las fases 1–4 después de añadir la API. Las pruebas recorren autenticación de cada ruta, filtros, agotados, datos del negocio, errores JSON, privacidad, exclusión de productos sin publicar y actualización inmediata del precio. Comparan el saldo HTTP con el saldo actual de Commerce Stock sin realizar compras ni modificar existencias.

Fuentes: controladores, suscriptor de autenticación y errores, servicios compartidos de catálogo/negocio y pruebas HTTP reales. La API queda preparada para el chatbot; aún no incluye el workflow ni el widget.
