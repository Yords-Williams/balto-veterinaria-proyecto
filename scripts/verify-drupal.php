<?php

/** Verifica resultados de instalación; falla si falta algún requisito base. */
$check = static function (bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  echo "OK: $message\n";
};
$modules = \Drupal::moduleHandler();
foreach (['commerce_product', 'commerce_cart', 'commerce_checkout', 'commerce_payment', 'commerce_stock', 'commerce_stock_local'] as $module) {
  $check($modules->moduleExists($module), "Módulo $module habilitado.");
}
$check(\Drupal::database()->driver() === 'mysql', 'Drupal usa MariaDB/MySQL.');
$check(\Drupal::config('system.site')->get('name') === 'BALTO Veterinaria', 'Nombre de la veterinaria.');
$check(\Drupal::config('system.site')->get('default_langcode') === 'es', 'Idioma español.');
$check((int) \Drupal::entityQuery('node')->condition('type', 'servicio')->accessCheck(FALSE)->count()->execute() === 7, 'Siete servicios cargados.');
$check((int) \Drupal::entityQuery('taxonomy_term')->condition('vid', 'categorias_productos')->accessCheck(FALSE)->count()->execute() === 5, 'Cinco categorías cargadas.');
$store = \Drupal\commerce_store\Entity\Store::loadMultiple();
$check(count($store) === 1 && reset($store)->getDefaultCurrencyCode() === 'PEN', 'Tienda de demostración en soles.');
$business = \Drupal::config('vet_api.business');
$check(count($business->get('addresses') ?? []) === 2, 'Dos sedes guardadas en configuración.');
$check($business->get('schedule_is_example') === TRUE, 'Horario del consultorio marcado como ejemplo.');
$check($business->get('email') === '', 'Correo real pendiente.');
$check(strlen(\Drupal\Core\Site\Settings::get('vet_api_key', '')) >= 32, 'API key presente en ajustes privados.');
