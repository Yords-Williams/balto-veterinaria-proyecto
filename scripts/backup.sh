#!/usr/bin/env bash
# Copia consistente con una pausa breve de Drupal y n8n; no elimina backups.
set -euo pipefail
cd "$(dirname "$0")/.."
root=$PWD
backup_root=$(realpath -m -- "${BALTO_BACKUP_DIR:-$root/backups}")
[[ "$backup_root" != / && "$backup_root" != "$root" ]] || { echo 'Elige una carpeta exclusiva para backups.' >&2; exit 2; }
env_file=${BALTO_ENV_FILE:-$root/.env}
[[ -f "$env_file" ]] || { echo 'No existe el archivo de entorno.' >&2; exit 2; }
compose=(docker compose --env-file "$env_file")
umask 077
mkdir -p "$backup_root"
chmod 0700 "$backup_root"
exec 9>"$backup_root/.backup.lock"
flock -n 9 || { echo 'Ya hay un backup en curso.' >&2; exit 1; }
# No arrancar una aplicación que ya estaba detenida antes de la copia.
running=$("${compose[@]}" ps --services --status running)
for service in mariadb drupal n8n; do
  grep -Fxq "$service" <<<"$running" || { echo "El servicio $service debe estar iniciado para realizar esta copia." >&2; exit 1; }
done
folder=$(mktemp -d "$backup_root/balto-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX")
paused=0
resume() {
  status=$?
  trap - EXIT INT TERM
  if ((paused)); then
    if ! "${compose[@]}" start drupal n8n; then
      echo 'No se pudo reiniciar la tienda; ejecuta docker compose up -d.' >&2
      status=1
    fi
  fi
  if ((status)); then echo "Backup incompleto; no usar: $folder" >&2; fi
  exit "$status"
}
trap resume EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# Marcar antes de detener: incluso una interrupción parcial debe restaurar servicios.
paused=1
"${compose[@]}" stop drupal n8n
"${compose[@]}" exec -T mariadb sh -c 'export MYSQL_PWD="$MARIADB_PASSWORD"; exec mariadb-dump --user="$MARIADB_USER" --single-transaction --quick --routines --events --triggers "$MARIADB_DATABASE"' | gzip > "$folder/mariadb.sql.gz"
"${compose[@]}" run --rm --no-deps -T --entrypoint tar drupal -czf - -C /opt/drupal/web/sites/default/files . > "$folder/drupal-files.tar.gz"
# n8n ya está detenido: se copia SQLite junto con sus credenciales cifradas.
"${compose[@]}" run --rm --no-deps -T --entrypoint tar n8n -czf - -C /home/node/.n8n . > "$folder/n8n-data.tar.gz"
cp "$env_file" "$folder/environment.env"
git rev-parse HEAD > "$folder/git-commit.txt"
(cd "$folder"; sha256sum mariadb.sql.gz drupal-files.tar.gz n8n-data.tar.gz environment.env git-commit.txt > SHA256SUMS)
# El marcador se escribe al final para distinguir una copia completa.
printf 'Copia completa UTC: %s\n' "$(date -u +%FT%TZ)" > "$folder/COMPLETE"
printf 'Backup privado completado: %s\n' "$folder"
