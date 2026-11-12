<?php

declare(strict_types=1);

namespace Drupal\vet_api\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;
use Drupal\commerce_payment\PluginForm\ManualPaymentAddForm;
use Drupal\commerce_payment\PluginForm\PaymentReceiveForm;
use Drupal\commerce_price\Price;

/** Usa el pago manual nativo, con la protección adicional del pedido cancelado. */
#[CommercePaymentGateway(
  id: 'vet_coordinated_manual',
  label: new TranslatableMarkup('Pago coordinado BALTO'),
  display_label: new TranslatableMarkup('Pago coordinado con el establecimiento'),
  modes: ['n/a' => new TranslatableMarkup('Confirmación manual')],
  forms: ['add-payment' => ManualPaymentAddForm::class, 'receive-payment' => PaymentReceiveForm::class],
  payment_type: 'payment_manual',
  requires_billing_information: FALSE,
)]
final class CoordinatedManual extends Manual {

  private function isCancelled(PaymentInterface $payment): bool {
    // Recargar para proteger también una pestaña administrativa antigua.
    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($payment->getOrderId());
    return !$order || $order->getState()->getId() === 'canceled';
  }

  public function buildPaymentOperations(PaymentInterface $payment) {
    $operations = parent::buildPaymentOperations($payment);
    if ($this->isCancelled($payment)) {
      $operations['receive']['access'] = FALSE;
    }
    return $operations;
  }

  public function receivePayment(PaymentInterface $payment, ?Price $amount = NULL) {
    if ($this->isCancelled($payment)) {
      throw new PaymentGatewayException('El pedido está cancelado. No se puede registrar un pago recibido.');
    }
    parent::receivePayment($payment, $amount);
  }

}
