<?php

/**
 * Semilla inicial idempotente. Ejecutar exclusivamente con drush php:script.
 * La marca de versión evita restaurar datos de ejemplo sobre ediciones reales.
 */
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_store\Entity\Store;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

$state = \Drupal::state();
if ($state->get('balto.seed.phase1.v2')) {
  echo "Los datos iniciales ya existen; se conservan los cambios del administrador.\n";
  return;
}

// Español forma parte del núcleo; las traducciones comunitarias se añaden luego.
if (!\Drupal::languageManager()->getLanguage('es')) {
  \Drupal\language\Entity\ConfigurableLanguage::createFromLangcode('es')->save();
}
\Drupal::configFactory()->getEditable('system.site')
  ->set('name', 'BALTO Veterinaria')
  ->set('slogan', 'Medicina para tu mascota')
  ->set('default_langcode', 'es')
  ->save();
\Drupal::configFactory()->getEditable('system.date')->set('timezone.default', 'America/Lima')->save();

if (!Currency::load('PEN')) {
  Currency::create([
    'currencyCode' => 'PEN',
    'name' => 'Sol peruano',
    'numericCode' => '604',
    'symbol' => 'S/',
    'fractionDigits' => 2,
  ])->save();
}

if (!Store::loadMultiple()) {
  Store::create([
    'type' => 'online',
    'uid' => 1,
    'name' => 'BALTO Veterinaria',
    // Correo técnico; no se presenta como contacto real del negocio.
    'mail' => getenv('DRUPAL_ADMIN_EMAIL') ?: 'admin@example.invalid',
    'default_currency' => 'PEN',
    'timezone' => 'America/Lima',
    'address' => [
      'country_code' => 'PE',
      'administrative_area' => 'PUN',
      'locality' => 'Juliaca',
      'address_line1' => 'Jr. Ignacio Miranda N° 220',
    ],
    'is_default' => TRUE,
  ])->save();
}

if (!NodeType::load('servicio')) {
  NodeType::create([
    'type' => 'servicio',
    'name' => 'Servicio',
    'description' => 'Servicios reales de BALTO Veterinaria.',
  ])->save();
}

// No se presupone que el perfil estándar haya creado el almacenamiento body.
// La creación explícita también permite reintentar una semilla interrumpida.
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create([
    'field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_with_summary',
    'cardinality' => 1,
  ])->save();
}
if (!FieldConfig::loadByName('node', 'servicio', 'body')) {
  FieldConfig::create([
    'field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'servicio',
    'label' => 'Descripción', 'settings' => ['display_summary' => TRUE],
  ])->save();
}

$services = [
  'Medicina veterinaria en general' => 'Atención veterinaria general para tu mascota. Consulta por WhatsApp.',
  'Rayos X digital' => 'Servicio de diagnóstico mediante rayos X digital.',
  'Ecografías' => 'Servicio de ecografía veterinaria.',
  'Cirugías' => 'Servicio de cirugía veterinaria. Consulta la evaluación y disponibilidad por WhatsApp.',
  'Laboratorio' => 'Servicio de laboratorio veterinario.',
  'Internamiento' => 'Servicio de internamiento veterinario.',
  'Hospitalización veterinaria las 24 horas' => 'Hospitalización veterinaria disponible las 24 horas. Este horario no indica atención de tienda o consultorio las 24 horas.',
];
foreach ($services as $title => $body) {
  $existing = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'servicio', 'title' => $title,
  ]);
  if (!$existing) {
    Node::create([
      'type' => 'servicio', 'title' => $title, 'langcode' => 'es',
      'uid' => 1, 'status' => 1,
      'body' => ['value' => $body, 'format' => 'plain_text'],
    ])->save();
  }
  else {
    // Recupera una instalación interrumpida que guardó títulos antes del campo.
    $node = reset($existing);
    if ($node->get('body')->isEmpty()) {
      $node->set('body', ['value' => $body, 'format' => 'plain_text'])->save();
    }
  }
}

if (!Vocabulary::load('categorias_productos')) {
  Vocabulary::create(['vid' => 'categorias_productos', 'name' => 'Categorías de productos'])->save();
}
foreach (['Alimentos', 'Accesorios', 'Higiene y cuidado', 'Juguetes', 'Salud/Medicamentos de venta libre'] as $name) {
  if (!\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'categorias_productos', 'name' => $name])) {
    Term::create(['vid' => 'categorias_productos', 'name' => $name, 'langcode' => 'es'])->save();
  }
}

// Configuración en MariaDB, consultable por la futura API y editable en Fase 2.
\Drupal::configFactory()->getEditable('vet_api.business')
  ->set('name', 'BALTO Veterinaria')
  ->set('slogan', 'Medicina para tu mascota')
  ->set('city', 'Juliaca, Puno, Perú')
  ->set('currency', 'PEN')
  ->set('addresses', [
    'Jr. Ignacio Miranda N° 220, Juliaca',
    'Jr. Ignacio Miranda con Jr. Lima, Centro Comercial N° 3, Tienda N° 3',
  ])
  ->set('whatsapp', '986 866036')
  ->set('whatsapp_url', 'https://wa.me/51986866036')
  ->set('facebook_name', 'BALTO Veterinaria')
  ->set('facebook_url', '')
  ->set('email', '')
  ->set('hospitalization', '24 horas')
  ->set('consultation_schedule', 'Horario de ejemplo: lunes a sábado de 09:00 a 19:00. Pendiente de confirmación por BALTO.')
  ->set('schedule_is_example', TRUE)
  ->set('catalog_is_example', TRUE)
  ->save();

$state->set('balto.seed.phase1.v2', TRUE);
echo "Cargados: tienda PEN, siete servicios, cinco categorías y datos de BALTO.\n";
