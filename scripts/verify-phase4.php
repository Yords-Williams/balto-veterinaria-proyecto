<?php

/** Verificación de configuración persistida; no crea compras ni modifica stock. */
use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\user\Entity\Role;
use Drupal\Core\Field\Entity\BaseFieldOverride;

$check = static function (bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  echo "OK: $message\n";
};
$gateway = PaymentGateway::load('pago_simulado');
$check($gateway && $gateway->status() && $gateway->getPluginId() === 'vet_simulated_manual', 'Pasarela manual local habilitada.');
$check(str_contains($gateway->label(), 'No se realiza ningún cobro real'), 'Pago claramente rotulado como simulado.');
$check($gateway->getPlugin() instanceof \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual, 'Implementación basada en el pago manual nativo de Commerce.');
$check(CheckoutFlow::load('default')->getPluginId() === 'vet_checkout', 'Flujo de compra BALTO configurado.');
$check(!\Drupal::config('commerce_order.commerce_order_type.default')->get('sendReceipt'), 'Recibos de correo desactivados en la demo sin SMTP.');
$price = BaseFieldOverride::load('commerce_product_variation.vet_variation.price');
$check($price && $price->getSetting('available_currencies') === ['PEN'], 'Precios restringidos a soles.');
$manager = Role::load('administrador_tienda');
$client = Role::load('cliente');
$check($manager && !$manager->isAdmin() && $manager->hasPermission('create vet_product commerce_product') && $manager->hasPermission('update default commerce_order'), 'Administrador de tienda con permisos de productos y pedidos.');
$check($client && !$client->isAdmin() && $client->hasPermission('view own commerce_order'), 'Cliente con acceso a sus pedidos.');
foreach ([$manager, $client] as $role) {
  foreach (['administer users', 'administer modules', 'administer commerce_payment_gateway'] as $permission) {
    $check(!$role->hasPermission($permission), $role->label() . ': sin permiso ' . $permission . '.');
  }
  $accounts = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['roles' => $role->id(), 'status' => 1]);
  $check(count($accounts) > 0, 'Cuenta activa para ' . $role->label() . '.');
}
$catalog = \Drupal::service('vet_api.catalog');
foreach (\Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties(['type' => 'vet_variation']) as $variation) {
  $check($catalog->stock($variation) >= 0, 'Existencias no negativas: ' . $variation->getSku() . '.');
}
echo "Configuración de fase 4 verificada sin modificar pedidos.\n";
