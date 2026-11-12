<?php

declare(strict_types=1);

namespace Drupal\vet_api\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;
use Drupal\commerce_payment\PluginForm\ManualPaymentAddForm;
use Drupal\commerce_payment\PluginForm\PaymentReceiveForm;

/** Pago de demostración: solo un registro local, sin proveedor ni tarjetas. */
#[CommercePaymentGateway(
  id: 'vet_simulated_manual',
  label: new TranslatableMarkup('Pago manual simulado BALTO'),
  display_label: new TranslatableMarkup('Pago simulado – No se realiza ningún cobro real'),
  modes: ['n/a' => new TranslatableMarkup('Demostración local')],
  forms: ['add-payment' => ManualPaymentAddForm::class, 'receive-payment' => PaymentReceiveForm::class],
  payment_type: 'payment_manual',
  requires_billing_information: FALSE,
)]
final class SimulatedManual extends Manual {

  public function createPayment(PaymentInterface $payment, $received = FALSE) {
    // Se marca recibido únicamente para simular la compra académica.
    // Manual::createPayment solo persiste una entidad; no llama a ninguna red.
    parent::createPayment($payment, TRUE);
  }

}
