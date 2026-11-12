<?php

/** Prueba de integración del catálogo y del saldo real de Commerce Stock. */
$check = static function (bool $ok, string $message): void {
  if (!$ok) {
    throw new RuntimeException($message);
  }
  echo "OK: $message\n";
};
$check(\Drupal::moduleHandler()->moduleExists('commerce_stock_enforcement'), 'Validación nativa de stock habilitada.');
$check(\Drupal::moduleHandler()->moduleExists('commerce_stock_field'), 'Campo transaccional de stock habilitado.');
$products = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties(['type' => 'vet_product']);
$check(count($products) >= 15, 'Quince o más productos Commerce cargados.');
$catalog = \Drupal::service('vet_api.catalog');
$out = 0;
foreach ($products as $product) {
  $row = $catalog->present($product);
  $check($row['currency'] === 'PEN' && $row['category'] !== '' && $row['species'] !== [], 'Moneda, categoría y especie de ' . $row['sku'] . '.');
  $check(file_exists($product->get('field_photo')->entity->getFileUri()), 'Imagen local permanente de ' . $row['sku'] . '.');
  if (!$row['available']) {
    $out++;
  }
}
$check($out >= 2, 'Dos o más productos agotados para la demo.');
$variations = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties(['sku' => 'BAL-ALI-001']);
$variation = reset($variations);
$stock = $catalog->stock($variation);
$check($stock === 12, 'Dog Chow tiene doce unidades en el saldo de movimientos.');
$stores = $variation->getStores();
$context = new \Drupal\commerce\Context(\Drupal::currentUser(), reset($stores));
$manager = \Drupal::service('commerce_order.availability_manager');
$order_item = \Drupal::entityTypeManager()->getStorage('commerce_order_item')->createFromPurchasableEntity($variation, ['quantity' => '13']);
$check($manager->check($order_item, $context)->isUnavailable(), 'Commerce rechaza trece unidades cuando solo hay doce.');
$order_item->setQuantity('12');
$check($manager->check($order_item, $context)->isNeutral(), 'Commerce admite una cantidad igual al stock.');
$check(\Drupal::service('commerce_stock.service_manager')->getService($variation)->getId() === 'local_stock', 'Catálogo y validaciones usan el almacenamiento real de Commerce Stock.');
