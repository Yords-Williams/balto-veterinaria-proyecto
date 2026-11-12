<?php

/**
 * Configuración compartida por Drush y Apache. Los secretos vienen del entorno.
 */
$databases['default']['default'] = [
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASSWORD'),
  'host' => getenv('DB_HOST') ?: 'mariadb',
  'port' => getenv('DB_PORT') ?: '3306',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'prefix' => '',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');
$settings['config_sync_directory'] = '/opt/drupal/config/sync';
$settings['file_private_path'] = '/opt/drupal/private';
$settings['update_free_access'] = FALSE;
$settings['trusted_host_patterns'] = array_map(
  static fn(string $host): string => '^' . preg_quote(trim($host), '/') . '$',
  explode(',', getenv('DRUPAL_TRUSTED_HOSTS') ?: 'localhost,127.0.0.1,drupal'),
);

// No se exportan las claves a configuración ni se envían al navegador.
$settings['vet_api_key'] = getenv('VET_API_KEY');
$settings['vet_chat_webhook_url'] = getenv('CHAT_WEBHOOK_URL');
$settings['vet_chat_allowed_origin'] = getenv('CHAT_ALLOWED_ORIGIN');
