#!/usr/bin/env bash
# Idempotente: los reinicios no reinstalan Drupal ni cambian contraseñas.
set -euo pipefail
cd /opt/drupal

for name in DB_PASSWORD DRUPAL_HASH_SALT DRUPAL_ADMIN_PASSWORD VET_API_KEY; do
  if [[ -z "${!name:-}" || "${!name}" == CAMBIAR ]]; then
    printf 'Falta configurar %s en .env.\n' "$name" >&2
    exit 1
  fi
done

# Espera explícita adicional, útil al ejecutar el script manualmente.
php /opt/balto/scripts/wait-db.php

# Se consulta la tabla de sistema: no se confunde un error de bootstrap con
# una base vacía, evitando reinstalar accidentalmente una tienda existente.
installed="$(php /opt/balto/scripts/is-installed.php)"
if [[ "$installed" == no ]]; then
  echo 'Instalando Drupal y creando el administrador de demostración…'
  drush site:install standard --yes \
    --site-name='BALTO Veterinaria' \
    --site-mail="$DRUPAL_ADMIN_EMAIL" \
    --account-name="$DRUPAL_ADMIN_USER" \
    --account-mail="$DRUPAL_ADMIN_EMAIL" \
    --account-pass="$DRUPAL_ADMIN_PASSWORD"
else
  echo 'Drupal ya está instalado; se conservan los datos existentes.'
fi

drush --yes en language commerce_product commerce_cart commerce_checkout commerce_payment \
  commerce_order commerce_store commerce_stock commerce_stock_local
drush php:script /opt/balto/scripts/seed.php
drush cache:rebuild
chown -R www-data:www-data /opt/drupal/web/sites/default/files
echo 'BALTO: instalación base terminada. Apache puede iniciar.'
