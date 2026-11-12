<?php

/** Comprobación del sitio Drupal instalado, no de datos simulados. */
$check = static function (bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  echo "OK: $message\n";
};
$check(\Drupal::config('system.theme')->get('default') === 'vet_theme', 'Tema propio BALTO activo.');
$check(\Drupal::config('system.site')->get('page.front') === '/inicio', 'Portada dinámica como inicio.');
$check(\Drupal::moduleHandler()->moduleExists('vet_api'), 'Módulo de datos del negocio habilitado.');
$business = \Drupal::service('vet_api.business');
$data = $business->getBusiness();
$check(!array_key_exists('vet_api_key', $data), 'La fuente pública no expone la API key.');
$check(count($business->getServices()) === 7, 'La portada obtiene siete servicios de entidades Drupal.');
$check(!empty($data['about_title']) && !empty($data['about_text']), 'Presentación almacenada en configuración.');
$check(\Drupal\field\Entity\FieldConfig::loadByName('node', 'servicio', 'field_service_icon') !== NULL, 'Íconos editables en cada servicio.');
$check(\Drupal::moduleHandler()->moduleExists('locale'), 'Módulo de traducción de interfaz habilitado.');
$check((int) \Drupal::state()->get('balto.translations.v1') === 1, 'Traducciones oficiales importadas.');
