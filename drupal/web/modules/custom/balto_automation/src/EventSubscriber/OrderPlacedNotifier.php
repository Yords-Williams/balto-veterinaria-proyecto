<?php

declare(strict_types=1);

namespace Drupal\balto_automation\EventSubscriber;

use Drupal\balto_automation\Service\N8nNotifier;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Avisa a n8n cuando se realiza un pedido. */
final class OrderPlacedNotifier implements EventSubscriberInterface {
  public function __construct(
    private readonly N8nNotifier $notifier,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function getSubscribedEvents(): array {
    return ['commerce_order.place.post_transition' => 'onPlaced'];
  }

  public function onPlaced(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();
    $this->notifier->order($order, 'placed');

    // Checkout Pro puede completar el pago mientras el pedido sigue en borrador.
    $payments = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties([
      'order_id' => $order->id(),
      'state' => 'completed',
    ]);
    if ($payments && $order->getOrderNumber()) {
      $this->notifier->order($order, 'paid');
    }
  }

}
