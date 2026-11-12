#!/usr/bin/env python3
"""Genera credenciales locales aleatorias sin imprimirlas ni sobrescribir .env."""
from pathlib import Path
import os
import secrets

ROOT = Path(__file__).resolve().parents[1]
SECRET_KEYS = {
    'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'DRUPAL_HASH_SALT',
    'DRUPAL_ADMIN_PASSWORD', 'VET_API_KEY', 'N8N_ENCRYPTION_KEY',
}


def main():
    target = ROOT / '.env'
    if target.exists():
        print('El archivo .env ya existe; se conserva sin cambios.')
        return
    lines = []
    for line in (ROOT / '.env.example').read_text().splitlines():
        key = line.partition('=')[0]
        lines.append(f'{key}={secrets.token_hex(32)}' if key in SECRET_KEYS else line)
    # O_EXCL evita sobrescrituras y 0600 restringe la lectura al propietario.
    fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as output:
        output.write('\n'.join(lines) + '\n')
    print('Creado .env con secretos únicos. No lo subas a GitHub.')


if __name__ == '__main__':
    main()
