<?php

declare(strict_types=1);

namespace Drupal\balto_payments\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\balto_payments\PluginForm\CulqiOrdersForm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

#[CommercePaymentGateway(
  id: 'balto_culqi_orders',
  label: new TranslatableMarkup('BALTO Culqi: billeteras y PagoEfectivo'),
  display_label: new TranslatableMarkup('Yape / Plin por QR o banca móvil — Culqi'),
  forms: ['offsite-payment' => CulqiOrdersForm::class],
  payment_type: 'payment_manual',
  requires_billing_information: FALSE,
)]
final class CulqiOrdersGateway extends OffsitePaymentGatewayBase {

  public function onReturn(OrderInterface $order, Request $request) {
    $payments = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties([
      'order_id' => $order->id(), 'payment_gateway' => $this->parentEntity->id(),
    ]);
    if (count($payments) !== 1) {
      throw new PaymentGatewayException('No hay una orden de pago asociada.');
    }
    $payment = reset($payments);
    $state = \Drupal::service('balto_payments.orders')->synchronize($payment->getRemoteId(), $this->parentEntity->id());
    if (!in_array($state, ['created', 'pending', 'paid'], TRUE)) {
      throw new PaymentGatewayException('La orden de pago ya no está disponible.');
    }
    // Ni parámetros de la URL ni callbacks del navegador confirman un pago.
  }

  public function onNotify(Request $request) {
    if (!$this->parentEntity->status()) {
      return new JsonResponse(['status' => 'disabled'], 503);
    }
    if (!$request->isMethod('POST') || strlen($request->getContent()) > 16384) {
      throw new BadRequestHttpException('Solicitud no válida.');
    }
    try {
      $event = json_decode($request->getContent(), TRUE, 16, JSON_THROW_ON_ERROR);
      if (($event['type'] ?? '') !== 'order.status.changed') {
        return new JsonResponse(['status' => 'ignored'], 202);
      }
      $data = $event['data'] ?? [];
      if (is_string($data)) {
        $data = json_decode($data, TRUE, 16, JSON_THROW_ON_ERROR);
      }
      $id = is_array($data) ? ($data['id'] ?? '') : '';
      if (!is_string($id) || !preg_match('/^ord_(test|live)_[A-Za-z0-9]+$/D', $id)) {
        throw new \InvalidArgumentException();
      }
    }
    catch (\Throwable) {
      throw new BadRequestHttpException('Evento no válido.');
    }
    try {
      // El cuerpo solo aporta el ID. El estado y el importe se consultan a Culqi.
      $state = \Drupal::service('balto_payments.orders')->synchronize($id, $this->parentEntity->id());
      return new JsonResponse(['status' => $state === 'unknown' ? 'ignored' : 'received']);
    }
    catch (\Throwable) {
      // Un 503 permite que el proveedor reintente, sin exponer detalles internos.
      throw new ServiceUnavailableHttpException(30, 'Verificación temporalmente no disponible.');
    }
  }
}
