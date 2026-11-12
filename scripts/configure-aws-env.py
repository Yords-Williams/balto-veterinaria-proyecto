#!/usr/bin/env python3
"""Adapta solo las URL de una instalación AWS; nunca reemplaza secretos."""
import argparse
import ipaddress
import os
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def deployment_values(host, https=False):
    # Una IP IPv4 o un nombre DNS; nunca aceptar URL, puertos o sintaxis de Caddy.
    try:
        parsed = ipaddress.ip_address(host)
        if parsed.version != 4 or https:
            raise ValueError('Para HTTPS usa un dominio DNS; para HTTP usa IPv4.')
    except ValueError:
        if not re.fullmatch(r'(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}', host):
            raise ValueError('Host inválido. Introduce una IPv4 o un dominio sin protocolo ni puerto.')
    base = ('https://' if https else 'http://') + host.lower()
    return {
        'BALTO_DEPLOYMENT': 'aws',
        'BALTO_SITE_ADDRESS': host.lower() if https else base,
        'BIND_ADDRESS': '127.0.0.1',
        'DRUPAL_BASE_URL': base,
        'DRUPAL_TRUSTED_HOSTS': ','.join(dict.fromkeys([host.lower(), 'localhost', '127.0.0.1', 'drupal'])),
        'CHAT_WEBHOOK_URL': base + '/webhook/chat',
        'CHAT_ALLOWED_ORIGIN': base,
        'WEBHOOK_URL': base + '/',
        # Editor por túnel SSH; el proxy termina HTTPS para el webhook público.
        'N8N_HOST': 'localhost',
        'N8N_PROTOCOL': 'http',
        'N8N_EDITOR_BASE_URL': 'http://localhost:5678',
        'N8N_SECURE_COOKIE': 'false',
    }

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--host', required=True)
    parser.add_argument('--https', action='store_true')
    args = parser.parse_args()
    try:
        values = deployment_values(args.host, args.https)
    except ValueError as error:
        parser.error(str(error))
    target = ROOT / '.env'
    if not target.exists():
        parser.error('Primero ejecuta python3 scripts/init-env.py en la instancia nueva.')
    lines = target.read_text().splitlines()
    result = []
    for line in lines:
        key = line.partition('=')[0]
        if key in values:
            result.append(key+'='+values.pop(key))
        else:
            result.append(line)
    result.extend(k+'='+v for k,v in values.items())
    # Reemplazo atómico conservando permisos privados, sin imprimir credenciales.
    temporary = target.with_name('.env.aws-tmp')
    fd = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write('\n'.join(result)+'\n')
        os.replace(temporary, target)
    finally:
        temporary.unlink(missing_ok=True)
    print('URL pública configurada: '+('https://' if args.https else 'http://')+args.host)

if __name__ == '__main__':
    main()
