<?php

/** Actualización idempotente: tema, campos de servicios y presentación. */
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (\Drupal::state()->get('balto.seed.phase2.v3')) {
  echo "La configuración de Fase 2 ya existe; se conservan las ediciones.\n";
  return;
}
$service_type = NodeType::load('servicio');
$service_type->setDisplaySubmitted(FALSE);
$service_type->save();

$icons = [
  'stethoscope' => 'Estetoscopio: medicina general',
  'xray' => 'Radiografía',
  'ultrasound' => 'Ecografía',
  'surgery' => 'Cirugía',
  'lab' => 'Laboratorio',
  'bed' => 'Internamiento',
  'moon' => 'Hospitalización',
];
if (!FieldStorageConfig::loadByName('node', 'field_service_icon')) {
  FieldStorageConfig::create([
    'field_name' => 'field_service_icon', 'entity_type' => 'node',
    'type' => 'list_string', 'cardinality' => 1,
    'settings' => ['allowed_values' => $icons],
  ])->save();
}
if (!FieldConfig::loadByName('node', 'servicio', 'field_service_icon')) {
  FieldConfig::create([
    'field_name' => 'field_service_icon', 'entity_type' => 'node',
    'bundle' => 'servicio', 'label' => 'Ícono del servicio',
    'required' => TRUE, 'default_value' => [['value' => 'stethoscope']],
  ])->save();
}

$form = EntityFormDisplay::load('node.servicio.default') ?? EntityFormDisplay::create([
  'targetEntityType' => 'node', 'bundle' => 'servicio', 'mode' => 'default', 'status' => TRUE,
]);
$form->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
  ->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 1])
  ->setComponent('field_service_icon', ['type' => 'options_select', 'weight' => 2])
  ->save();
$display = EntityViewDisplay::load('node.servicio.default') ?? EntityViewDisplay::create([
  'targetEntityType' => 'node', 'bundle' => 'servicio', 'mode' => 'default', 'status' => TRUE,
]);
$display->setComponent('body', ['type' => 'text_default', 'label' => 'hidden'])->removeComponent('field_service_icon')->save();
$titles = [
  'Medicina veterinaria en general' => 'stethoscope', 'Rayos X digital' => 'xray',
  'Ecografías' => 'ultrasound', 'Cirugías' => 'surgery', 'Laboratorio' => 'lab',
  'Internamiento' => 'bed', 'Hospitalización veterinaria las 24 horas' => 'moon',
];
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'servicio']);
foreach ($nodes as $node) {
  $node->set('field_service_icon', $titles[$node->label()] ?? 'stethoscope')->save();
}

$config = \Drupal::configFactory()->getEditable('vet_api.business');
if (!$config->get('about_title')) {
  $config->set('about_title', 'Porque ellos también son familia.');
}
if (!$config->get('about_text')) {
  $config->set('about_text', 'Somos BALTO Veterinaria, en Juliaca. Acompañamos el cuidado de tu mascota con medicina veterinaria, diagnóstico, cirugía y hospitalización.

Creemos en una atención cercana y en conversar contigo sobre lo que necesita. Escríbenos por WhatsApp para conocer nuestros servicios y coordinar tu visita.');
}
$config->save();

\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/inicio')->save();
\Drupal::configFactory()->getEditable('language.negotiation')
  ->set('url.prefixes.es', '')->set('url.prefixes.en', 'en')->save();
\Drupal::service('theme_installer')->install(['vet_theme']);
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'vet_theme')->save();

// Al usar un tema sin herencia, se colocan explícitamente los bloques esenciales.
$blocks = \Drupal::entityTypeManager()->getStorage('block');
// Drupal clona bloques del tema anterior al instalar uno nuevo. Desactivar
// esas copias automáticas evita dos contenidos principales y títulos repetidos.
foreach ($blocks->loadByProperties(['theme' => 'vet_theme']) as $block) {
  if (str_starts_with($block->id(), 'vet_theme_')) {
    $block->disable()->save();
  }
}
foreach (['balto_content' => ['system_main_block', 'content'], 'balto_messages' => ['system_messages_block', 'highlighted']] as $id => [$plugin, $region]) {
  if (!$blocks->load($id)) {
    $blocks->create([
      'id' => $id, 'theme' => 'vet_theme', 'region' => $region, 'plugin' => $plugin,
      'settings' => ['id' => $plugin, 'label_display' => FALSE], 'weight' => 0, 'status' => TRUE,
    ])->save();
  }
}
// Los títulos de páginas interiores los proporciona Drupal; evitar duplicar
// el h1 diseñado dentro de la portada.
if (!$blocks->load('balto_page_title')) {
  $blocks->create([
    'id' => 'balto_page_title', 'theme' => 'vet_theme', 'region' => 'content',
    'plugin' => 'page_title_block', 'weight' => -10, 'status' => TRUE,
    'settings' => ['id' => 'page_title_block', 'label_display' => FALSE],
    'visibility' => ['request_path' => ['id' => 'request_path', 'negate' => TRUE, 'pages' => "<front>\n/tienda"]],
  ])->save();
}

\Drupal::state()->set('balto.seed.phase2.v3', TRUE);
echo "Fase 2: tema BALTO, portada dinámica y campo de íconos configurados.\n";
