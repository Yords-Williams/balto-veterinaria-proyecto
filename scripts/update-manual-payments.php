<?php

/** Habilita pagos coordinados sin tocar pedidos, pagos ni stock existentes. */
use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\commerce_payment\Entity\PaymentGateway;

if (\Drupal::state()->get('balto.manual_payments.v2')) {
  return;
}
foreach ([
  'pagar_al_recoger' => ['Pagar al recoger en BALTO', -20, 'Tu pago está pendiente. Confirma la disponibilidad y el retiro con BALTO por WhatsApp. Pagarás en el establecimiento.'],
  'yape_coordinado' => ['Yape — coordinar primero por WhatsApp', -10, 'Tu pago está pendiente. BALTO confirmará disponibilidad y monto antes de indicarte el pago. La confirmación del dinero recibido la realiza el negocio.'],
] as $id => [$label, $weight, $instructions]) {
  $gateway = PaymentGateway::load($id);
  if ($gateway && $gateway->getPluginId() === 'manual') {
    $gateway->setPluginId('vet_coordinated_manual');
    $gateway->save();
  }
  if (!$gateway) {
    PaymentGateway::create([
      'id' => $id, 'label' => $label, 'status' => TRUE, 'weight' => $weight,
      'plugin' => 'vet_coordinated_manual', 'configuration' => [
        'display_label' => $label, 'mode' => 'n/a', 'collect_billing_information' => FALSE,
        'instructions' => ['value' => $instructions, 'format' => 'plain_text'],
      ], 'conditions' => [], 'conditionOperator' => 'AND',
    ])->save();
  }
}
$flow = CheckoutFlow::load('default');
$configuration = $flow->getPlugin()->getConfiguration();
$configuration['panes']['completion_message']['step'] = '_disabled';
$configuration['panes']['vet_order_confirmation'] = ['step' => 'complete', 'weight' => 6];
$flow->set('label', 'Pedidos BALTO: retiro y pago coordinado');
$flow->getPlugin()->setConfiguration($configuration);
$flow->save();
\Drupal::state()->set('balto.manual_payments.v2', TRUE);
echo "Pagos al recoger, Yape coordinado y demostración disponibles.\n";
