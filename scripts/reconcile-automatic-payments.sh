#!/usr/bin/env bash
# Consulta pagos pendientes. No crea pagos nuevos ni necesita claves como argumentos.
set -euo pipefail
project_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
project_dir="${BALTO_PROJECT_DIR:-$project_dir}"
cd "$project_dir"
if [[ -z "${DOCKER_HOST:-}" && -S "/run/user/$(id -u)/balto-docker.sock" ]]; then
  export DOCKER_HOST="unix:///run/user/$(id -u)/balto-docker.sock"
fi
exec docker compose exec -T drupal vendor/bin/drush php:eval '\Drupal::service("balto_payments.orders")->reconcile();'
