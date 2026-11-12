<?php

declare(strict_types=1);

namespace Drupal\vet_api\Stock;

use Drupal\commerce\Context;
use Drupal\commerce_order\AvailabilityCheckerInterface;
use Drupal\commerce_order\AvailabilityResult;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_stock\StockServiceManagerInterface;

/** Valida el saldo en los procesos de disponibilidad de Commerce, en servidor. */
final class StockAvailabilityChecker implements AvailabilityCheckerInterface {

  public function __construct(private readonly StockServiceManagerInterface $stockManager) {}

  public function applies(OrderItemInterface $order_item): bool {
    $variation = $order_item->getPurchasedEntity();
    return $variation instanceof ProductVariationInterface && $variation->bundle() === 'vet_variation';
  }

  public function check(OrderItemInterface $order_item, Context $context): AvailabilityResult {
    $variation = $order_item->getPurchasedEntity();
    $service = $this->stockManager->getService($variation);
    $locations = $service->getConfiguration()->getAvailabilityLocations($context, $variation);
    $stock = $service->getStockChecker()->getTotalAvailableStockLevel($variation, $locations);
    if ((float) $order_item->getQuantity() > (float) $stock) {
      return AvailabilityResult::unavailable('La cantidad solicitada supera las existencias disponibles.', 'balto_insufficient_stock');
    }
    return AvailabilityResult::neutral();
  }

}
