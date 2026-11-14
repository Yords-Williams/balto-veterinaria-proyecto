<?php

/** Prepara la pasarela desactivada. No crea cuentas ni transacciones remotas. */
use Drupal\commerce_payment\Entity\PaymentGateway;

$id = 'culqi_automatico';
if (!PaymentGateway::load($id)) {
  PaymentGateway::create([
    'id' => $id, 'label' => 'Culqi automático — configurar antes de activar',
    'status' => FALSE, 'weight' => -30, 'plugin' => 'balto_culqi_orders',
    'configuration' => ['mode' => 'test', 'display_label' => 'Yape / Plin por QR o banca móvil — Culqi', 'collect_billing_information' => FALSE],
    'conditions' => [], 'conditionOperator' => 'AND',
  ])->save();
}
echo "Pasarela Culqi preparada en pruebas. Sigue desactivada hasta configurar credenciales.\n";
