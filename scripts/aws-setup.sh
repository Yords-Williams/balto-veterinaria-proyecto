#!/usr/bin/env bash
# Ubuntu 24.04 EC2. No crea recursos de AWS ni modifica Security Groups.
set -euo pipefail
usage() { echo 'Uso: sudo bash aws-setup.sh --repo URL_GITHUB --host IP_O_DOMINIO [--https] [--ref main] [--prepare-only]'; }
repo='' host='' ref='main' https=0 prepare_only=0
while (($#)); do
  case "$1" in
    --repo|--host|--ref)
      (($# >= 2)) || { usage >&2; exit 2; }
      case "$1" in --repo) repo=$2;; --host) host=$2;; --ref) ref=$2;; esac
      shift 2;;
    --https) https=1; shift;;
    --prepare-only) prepare_only=1; shift;;
    --help|-h) usage; exit 0;;
    *) usage >&2; exit 2;;
  esac
done
[[ $EUID == 0 && -n "$repo" && -n "$host" ]] || { usage >&2; exit 2; }
# Impedir URLs con credenciales embebidas y esquemas de ejecución arbitraria.
[[ "$repo" =~ ^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(\.git)?$ || "$repo" =~ ^git@github\.com:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(\.git)?$ ]] || { echo 'Usa una URL GitHub HTTPS o SSH sin tokens.' >&2; exit 2; }
[[ "$ref" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*$ ]] || { echo 'Referencia Git inválida.' >&2; exit 2; }
[[ "$host" =~ ^[A-Za-z0-9.-]+$ ]] || { echo 'Host inválido.' >&2; exit 2; }
source /etc/os-release
[[ "$ID" == ubuntu && "$VERSION_ID" == 24.04 ]] || { echo 'Este script requiere Ubuntu 24.04 LTS.' >&2; exit 2; }
[[ "$(uname -m)" == x86_64 ]] || { echo 'Usa la AMI x86_64 de Ubuntu para t3.medium.' >&2; exit 2; }
app_user=${SUDO_USER:-ubuntu}
id "$app_user" >/dev/null
[[ "$app_user" != root ]] || { echo 'Ejecuta con sudo desde el usuario ubuntu.' >&2; exit 2; }
app=/opt/balto-veterinaria
exec 9>/run/balto-setup.lock
flock -n 9 || { echo 'Ya hay un aprovisionamiento en curso.' >&2; exit 1; }
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl git python3
# Método del repositorio oficial de Docker; evita ejecutar un instalador remoto.
if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  arch=$(dpkg --print-architecture)
  cat > /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: noble
Components: stable
Architectures: $arch
Signed-By: /etc/apt/keyrings/docker.asc
EOF
  apt-get update
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker
# Compose moderno es necesario para borrar publicaciones de puertos con !override.
version=$(docker compose version --short | sed 's/^v//')
dpkg --compare-versions "$version" ge 2.24.4 || { echo 'Actualiza Docker Compose a 2.24.4 o superior.' >&2; exit 1; }
if [[ ! -d "$app/.git" ]]; then
  [[ ! -e "$app" ]] || [[ -d "$app" && -z "$(ls -A "$app")" ]] || { echo 'El directorio de destino no está vacío.' >&2; exit 1; }
  install -d -o "$app_user" -g "$(id -gn "$app_user")" "$app"
  # Para repos privados, configura primero la clave de despliegue del usuario.
  runuser -u "$app_user" -- env GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND='ssh -o BatchMode=yes -o StrictHostKeyChecking=yes' git clone --branch "$ref" --single-branch -- "$repo" "$app"
else
  current=$(runuser -u "$app_user" -- git -C "$app" remote get-url origin)
  [[ "$current" == "$repo" ]] || { echo 'El repositorio existente tiene otro origen; no se modifica.' >&2; exit 1; }
  [[ "$(runuser -u "$app_user" -- git -C "$app" branch --show-current)" == "$ref" ]] || { echo 'La rama instalada es distinta; revísala antes de actualizar.' >&2; exit 1; }
  runuser -u "$app_user" -- git -C "$app" diff --quiet
  runuser -u "$app_user" -- git -C "$app" diff --cached --quiet
  runuser -u "$app_user" -- env GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND='ssh -o BatchMode=yes -o StrictHostKeyChecking=yes' git -C "$app" pull --ff-only
fi
# Swap de 2 GiB: margen para construir Composer en 4 GiB de RAM.
if [[ ! -e /swapfile-balto ]]; then
  (umask 077; dd if=/dev/zero of=/swapfile-balto bs=1M count=2048 status=none)
  mkswap /swapfile-balto
fi
[[ "$(blkid -p -s TYPE -o value /swapfile-balto)" == swap ]] || { echo '/swapfile-balto existe y no es swap; no se modifica.' >&2; exit 1; }
chmod 0600 /swapfile-balto
if ! swapon --show=NAME --noheadings | grep -Fxq /swapfile-balto; then swapon /swapfile-balto; fi
if ! grep -Fq '/swapfile-balto ' /etc/fstab; then echo '/swapfile-balto none swap sw 0 0' >> /etc/fstab; fi
printf 'vm.swappiness=10\n' > /etc/sysctl.d/90-balto.conf
sysctl -p /etc/sysctl.d/90-balto.conf
# Nunca reutilizar por accidente el .env de desarrollo en un servidor público.
if [[ -f "$app/.env" ]] && ! grep -Fxq 'BALTO_DEPLOYMENT=aws' "$app/.env"; then
  echo 'Existe un .env sin marca AWS. No se reutilizan credenciales de desarrollo.' >&2
  exit 1
fi
runuser -u "$app_user" -- python3 "$app/scripts/init-env.py"
args=(--host "$host")
((https == 0)) || args+=(--https)
runuser -u "$app_user" -- python3 "$app/scripts/configure-aws-env.py" "${args[@]}"
install -o "$app_user" -g "$(id -gn "$app_user")" -m 0644 "$app/deploy/docker-compose.aws.yml" "$app/docker-compose.override.yml"
cd "$app"
docker compose config --quiet
if ((prepare_only)); then
  echo 'Preparación terminada sin inicializar los volúmenes. Lista para restauración.'
  exit 0
fi
docker compose up -d --build
printf '\nArranque solicitado. Proyecto: %s\nRevisa: sudo docker compose ps -a\nCredenciales privadas: %s/.env\n' "$app" "$app"
