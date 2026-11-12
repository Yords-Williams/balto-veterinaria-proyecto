#!/usr/bin/env bash
# Alternativa de desarrollo para Linux x86_64 sin acceso al Docker del sistema.
# No cambia el contexto global ni configura un servicio del sistema.
set -euo pipefail
if docker info >/dev/null 2>&1; then
  echo 'Docker ya está disponible; puedes usar docker compose normalmente.'
  exit 0
fi
runtime="${XDG_RUNTIME_DIR:-/run/user/$(id -u)}"
socket="unix://$runtime/balto-docker.sock"
if docker --host="$socket" info >/dev/null 2>&1; then
  printf 'El motor local BALTO ya está activo. Ejecuta en tu terminal:\nexport DOCKER_HOST=%q\n' "$socket"
  exit 0
fi
if [[ "$(uname -s)" != Linux || "$(uname -m)" != x86_64 || ! -w "$runtime" ]]; then
  echo 'Esta alternativa requiere Linux x86_64 y una sesión de usuario con XDG_RUNTIME_DIR escribible. Usa Docker Desktop o configura Docker Engine.' >&2
  exit 1
fi
for binary in dockerd newuidmap newgidmap curl tar; do
  command -v "$binary" >/dev/null || { printf 'Falta la dependencia %s.\n' "$binary" >&2; exit 1; }
done
base="${XDG_DATA_HOME:-$HOME/.local/share}/balto-dev"
tools="$base/rootless/docker-rootless-extras"
mkdir -p "$base/rootless"
if [[ ! -x "$tools/rootlesskit" ]]; then
  archive="$(mktemp)"
  trap 'rm -f "$archive"' EXIT
  curl -fsSL --retry 3 https://download.docker.com/linux/static/stable/x86_64/docker-rootless-extras-29.8.2.tgz -o "$archive"
  tar -xzf "$archive" -C "$base/rootless"
fi
if [[ ! -x "$tools/slirp4netns" ]]; then
  curl -fsSL --retry 3 https://github.com/rootless-containers/slirp4netns/releases/download/v1.3.7/slirp4netns-x86_64 -o "$tools/slirp4netns"
  chmod +x "$tools/slirp4netns"
fi
nohup env PATH="$tools:$PATH" \
  DOCKERD_ROOTLESS_ROOTLESSKIT_NET=slirp4netns \
  DOCKERD_ROOTLESS_ROOTLESSKIT_STATE_DIR="$runtime/balto-rootless" \
  "$tools/dockerd-rootless.sh" \
  --host="$socket" --data-root="$base/docker" \
  --exec-root="$runtime/balto-docker-exec" --pidfile="$runtime/balto-docker.pid" \
  --storage-driver=overlay2 > "$base/docker.log" 2>&1 < /dev/null &
for attempt in {1..30}; do
  if docker --host="$socket" info >/dev/null 2>&1; then
    printf 'Motor local activo. Ejecuta en tu terminal:\nexport DOCKER_HOST=%q\n' "$socket"
    exit 0
  fi
  sleep 1
done
printf 'No inició el motor local. Revisa %s/docker.log y los requisitos de Docker Rootless.\n' "$base" >&2
exit 1
