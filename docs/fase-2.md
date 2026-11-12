# Fase 2: la página de BALTO Veterinaria

## Qué se incorpora

- Tema propio `vet_theme` con Twig, CSS y JavaScript puro; diseño primero para celular, colores verde oscuro, blanco cálido y amarillo.
- Portada con el lema, fotografía ilustrativa local y botón **Separa tu cita por WhatsApp**. Abre el número real con un mensaje precargado; no crea un sistema de citas.
- Siete servicios consultados desde entidades `Servicio`. Cada entidad guarda título, descripción y un ícono seleccionable.
- Presentación de BALTO, dos sedes, mapa de la sede central, horarios y contacto.
- Hospitalización de 24 horas presentada de forma separada del horario de tienda/consultorio, que permanece marcado como **de ejemplo y por confirmar**.
- Accesos a tienda y carrito. La tienda tiene una página informativa hasta que se implemente el catálogo en Fase 3; no simula productos ni compras.
- Correo real sin publicar hasta que se confirme. Facebook muestra el nombre proporcionado y solo se convierte en enlace cuando se guarda una URL real.
- Formulario protegido para cambiar la información pública. Twig y el futuro chatbot compartirán el servicio `vet_api.business`.
- Traducciones oficiales de Drupal y Commerce empaquetadas en el repositorio e importadas una vez.

## Abrir la página

1. Levanta la instalación con `docker compose up -d --build`.
2. Espera a que termine `drupal-init` y abre <http://localhost:8080>.
3. Pulsa **Servicios**, **Nosotros** o **Encuéntranos** para navegar por la portada.
4. Abre el menú desde el botón de tres líneas si estás en celular; puedes cerrarlo con Escape.

**Resultado esperado:** portada BALTO con siete servicios, dos sedes y contacto por WhatsApp. n8n está disponible en <http://localhost:5678>, pero todavía no contiene el chatbot.

## Cambiar horarios, sedes y contacto

1. Inicia sesión en <http://localhost:8080/user/login> con las credenciales de tu `.env`.
2. Abre **Configuración → Sistema → Datos y horarios de BALTO**, o entra a <http://localhost:8080/admin/config/balto/negocio>.
3. Edita los campos del negocio. Para WhatsApp, escribe un celular peruano de nueve dígitos.
4. Mantén marcada la opción de horario de ejemplo hasta confirmar la atención real de tienda y consultorio.
5. Guarda la configuración.
6. Abre la portada en una ventana privada para comprobar el cambio.

**Resultado esperado:** la portada muestra los valores nuevos y se invalida su caché automáticamente. Los visitantes sin permiso no pueden abrir el formulario. La API key interna no aparece ni en este formulario ni en la fuente pública de datos.

El botón de WhatsApp se deriva del número guardado, evitando tener que editar varios enlaces. El mapa y el enlace **Cómo llegar** se derivan de la dirección de la sede central. Una URL de Facebook debe ser HTTPS y pertenecer a Facebook.

## Editar un servicio

1. Inicia sesión como administrador.
2. Abre **Contenido** en <http://localhost:8080/admin/content>.
3. Busca un contenido de tipo **Servicio** y abre **Editar**.
4. Cambia su título, descripción o ícono.
5. Guarda.
6. Revisa la tarjeta en la portada.

**Resultado esperado:** los cambios se reflejan sin modificar plantillas ni JavaScript. Los servicios despublicados dejan de mostrarse al visitante.

## Incorporar el logo real

1. Copia el PNG real a `drupal/web/themes/custom/vet_theme/images/logo.png`.
2. Reconstruye el proyecto con `docker compose up -d --build`.
3. Recarga la portada.

**Resultado esperado:** el tema usa el PNG automáticamente. Mientras no exista, se muestra una cabeza de lobo provisional. La fotografía ilustrativa y los SVG tienen sus fuentes documentadas en [recursos visuales](recursos-visuales.md).

## Verificar

Resultado registrado: **48 comprobaciones de navegador correctas, sin errores JavaScript ni recursos locales fallidos**. También se verificaron el bootstrap de Drupal, la conexión a MariaDB, los módulos, la tienda en PEN y la salud de n8n. Se comprobó la semilla en una base vacía separada.

```bash
bash scripts/verify.sh
deno run --node-modules-dir=none --allow-all tests/phase2-browser.mjs
```

La prueba del navegador usa Puppeteer y un navegador Chromium compatible; su ruta predeterminada corresponde a esta computadora. En otra máquina, configura `BALTO_BROWSER` con la ruta de tu navegador antes de ejecutar la prueba. Deno y el navegador son herramientas de desarrollo; no se necesitan en el servidor de la demo.

El script comprueba anchos de 360, 390, 768, 1024 y 1440 píxeles, enlaces, imágenes, menú, protección del formulario, validación de teléfono y edición real de horario y servicio. Restaura los valores editados después de comprobarlos. Durante esta prueba se intercepta únicamente el iframe externo de Google Maps para evitar que la verificación dependa de Google; su URL y dirección se comprueban igualmente.

El informe se escribe en `docs/verificacion-fase-2.json`; las capturas están en `docs/capturas/`. Consulta los resultados registrados antes de considerar aprobada la fase.

## Decisiones técnicas

La configuración simple de Drupal almacena horarios y contacto en MariaDB. El tipo de contenido `Servicio` permite agregar, editar o despublicar prestaciones desde el panel. Los controles de acceso y los formularios usan permisos, validaciones y protección CSRF de Drupal.

El renderizado agrega etiquetas de caché para la configuración del negocio y los contenidos. Así se mantiene una portada cacheada sin que sus datos queden desactualizados al editar el panel. No hay claves privadas dentro de Twig ni del JavaScript público.

Se siguieron la [definición oficial de temas](https://www.drupal.org/docs/develop/theming-drupal/defining-a-theme-with-an-infoyml-file) y la [API de configuración de Drupal](https://www.drupal.org/docs/drupal-apis/configuration-api/simple-configuration-api).
