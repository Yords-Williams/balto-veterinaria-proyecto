# Recursos visuales

## Fotografía de portada

- Archivo local: `drupal/web/themes/custom/vet_theme/images/companeros.jpg`.
- Autor: Alvan Nee.
- Fuente: [Two brown and white dogs running dirt road during daytime — Unsplash](https://unsplash.com/photos/two-brown-and-white-dogs-running-dirt-road-during-daytime-T-0EW-SEbsE).
- Licencia indicada en la ficha: Unsplash License.
- Uso: imagen ilustrativa de mascotas. No representa pacientes, personal ni instalaciones de BALTO.
- Se descarga y sirve desde el propio proyecto para evitar solicitudes a un CDN durante la demo.

## Marca e íconos

`logo-placeholder.svg` es una marca provisional creada para el proyecto; no pretende ser el logo oficial. Se reemplaza automáticamente por `images/logo.png` si el usuario aporta ese archivo y se reconstruye la imagen Docker.

Los íconos lineales son SVG propios del tema. Cada servicio guarda la elección del ícono en su entidad Drupal. La marca BALTO y sus datos fueron proporcionados por el usuario.

## Mapa

El iframe consulta Google Maps con la dirección central almacenada en Drupal. Necesita conexión a internet. La dirección textual y el enlace para abrir Maps permanecen disponibles aunque no cargue el iframe.
