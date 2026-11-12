# Fase 1: infraestructura y preparación de Drupal

## Decisiones y compatibilidad

- Imagen oficial `drupal:11.4.12-php8.4-apache-bookworm`. Se comprobó que la etiqueta figura en el [registro oficial de Docker](https://github.com/docker-library/official-images/blob/master/library/drupal) y que la [receta oficial](https://github.com/docker-library/drupal/blob/master/11.4/php8.4/apache-bookworm/Dockerfile) incluye GD, PDO MySQL, ZIP, Composer, Apache y rewrite. Nuestro Dockerfile añade Intl, BCMath y cliente MariaDB para Drush.
- Drupal 11.4.12, Commerce 3.3.14, Commerce Stock 3.0.0 y Drush 13.8.1 quedaron resueltos en `drupal/composer.lock` mediante Composer. PHP objetivo: 8.4.0. [Commerce documenta compatibilidad con Drupal 11](https://docs.drupalcommerce.org/v2/installation/); [Commerce Stock 3.0.0 admite Drupal 10.2 y 11](https://www.drupal.org/project/commerce_stock/releases/3.0.0). Se verificaron además los archivos `.info.yml` descargados.
- Se mantienen restricciones exactas para el núcleo, alineadas con la imagen. Composer advierte sobre restricciones exactas; son intencionales para esta entrega. Las actualizaciones deberán cambiar imagen y dependencias juntas y repetir pruebas.
- MariaDB 11.4, sin puerto publicado, con volumen persistente y `healthcheck.sh` de su imagen. El nombre de servicio `mariadb` funciona como DNS dentro de Docker.
- n8n 2.47.4, versión estable indicada por la [documentación oficial consultada](https://docs.n8n.io/hosting/installation/docker/). Guarda sus workflows y usuarios en SQLite persistente; no necesita RDS ni otro contenedor de base de datos para esta demo. No se agrega un proveedor externo de IA.
- `drupal-init` es un contenedor auxiliar que termina. Reduce carreras entre solicitudes web e instalación y evita iniciar una tienda a medio instalar.
- Los puertos 8080 y 5678 se publican solo en `127.0.0.1` por defecto. La exposición pública, proxy, HTTPS y URLs públicas se resolverán en Fase 9.
- Se genera `.env` local con claves independientes y permisos `0600`; no hay credenciales reales en archivos versionados. La clave de API se guarda en ajustes de servidor, sin enviarla al navegador.

## Verificaciones ejecutadas

Ejecutadas en el entorno de trabajo:

- Descarga e instalación Composer de las 107 dependencias resueltas. En el PHP del equipo se omitieron exclusivamente los requisitos `ext-gd` y `ext-intl` para esa instalación; no se omiten en el Dockerfile. PHP local: 8.5; plataforma objetivo declarada: 8.4. Por ello esta instalación local no sustituye la prueba dentro del contenedor.
- Auditoría de Composer durante la resolución: sin avisos de vulnerabilidades conocidos.
- `docker compose config --quiet`: correcto con el `.env` generado.
- `bash -n scripts/install.sh scripts/verify.sh`: correcto.
- `php -l` en ajustes y scripts PHP: correcto.
- Generación de secretos sin imprimirlos y conservación del `.env` existente en una segunda ejecución.

## Límite de verificación

El motor Docker no está accesible: la comprobación inicial devolvió permiso denegado y la comprobación posterior con autorización devolvió socket inexistente. `sudo -n docker info` requiere contraseña. No se ejecutó con éxito una construcción ni un arranque de los contenedores.

También se exploró una base MariaDB temporal separada de cualquier proyecto; no sustituye una prueba de la imagen fijada. No se afirma haber instalado Drupal ni ejecutado su semilla en una base viva.

**Fase 1 todavía no está verificada de punta a punta.** No se pasa a Fase 2 hasta confirmar el arranque y recibir la confirmación solicitada por el usuario.

## Prueba pendiente con Docker activo

Desde la raíz del proyecto:

```bash
python3 scripts/init-env.py
docker compose up -d
docker compose logs -f drupal-init
docker compose ps -a
bash scripts/verify.sh
```

Resultado esperado: instalador con salida 0; los otros tres servicios saludables; comprobaciones de MariaDB, módulos Commerce, moneda, servicios, categorías, direcciones y claves correctas.

Para comprobar idempotencia:

```bash
docker compose run --rm drupal-init
bash scripts/verify.sh
docker compose restart drupal n8n mariadb
bash scripts/verify.sh
```

Tras reiniciar, espera a que MariaDB esté sano antes de repetir la verificación. Deben conservarse los siete servicios, cinco categorías y una sola tienda; no se debe cambiar la contraseña del administrador.

## Lo que se entregará después

La Fase 1 carga una semilla inicial de datos reales del negocio. Los quince productos, fotos y configuración de control de stock se implementarán en Fase 3; la pasarela simulada y roles en Fase 4. La API y el workflow no se sustituyen por archivos vacíos que pretendan estar operativos: sus carpetas están reservadas para Fases 5 y 6.
