<?php

/** Integración Drupal real con HTTP simulado. Nunca llama a Culqi ni mueve dinero. */
use Drupal\balto_payments\Service\CulqiOrders;
use Drupal\commerce_price\Price;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

$results = [];
$check = function (bool $ok, string $label) use (&$results): void {
  if (!$ok) { throw new RuntimeException($label); }
  $results[] = $label;
  echo "OK: $label\n";
};
$reject = function (callable $fn, string $label) use ($check): void {
  $caught = FALSE;
  try { $fn(); } catch (\Drupal\commerce_payment\Exception\PaymentGatewayException) { $caught = TRUE; }
  $check($caught, $label);
};
$entities = \Drupal::entityTypeManager();
$database = \Drupal::database();
$gateway = $entities->getStorage('commerce_payment_gateway')->load('culqi_automatico');
$check($gateway && !$gateway->status() && $gateway->getPlugin()->getMode() === 'test', 'Pasarela desactivada y en pruebas por defecto');
$mock = new MockHandler();
$history = [];
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$service = new CulqiOrders(new Client(['handler' => $stack]), $entities, \Drupal::lock(), $database, \Drupal::service('logger.factory'), \Drupal::configFactory());
$check(CulqiOrders::cents('189.900000') === 18990 && CulqiOrders::cents('0.01') === 1, 'Importes decimales exactos en céntimos');
$reject(fn() => CulqiOrders::cents('1.001'), 'Se rechazan fracciones menores a un céntimo');
$reject(fn() => $service->credentials('test'), 'Sin credenciales no se puede iniciar un pago');
putenv('BALTO_CULQI_PUBLIC_KEY=pk_test_BALTOFixture');
putenv('BALTO_CULQI_SECRET_KEY=sk_live_Mismatch');
$reject(fn() => $service->credentials('test'), 'Las llaves de entornos distintos se rechazan');
putenv('BALTO_CULQI_SECRET_KEY=sk_test_BALTOFixture');
$service->credentials('test');
putenv('BALTO_CULQI_PUBLIC_KEY=pk_live_BALTOFixture');
putenv('BALTO_CULQI_SECRET_KEY=sk_live_BALTOFixture');
$reject(fn() => $service->credentials('live'), 'Producción bloqueada sin habilitación explícita');
putenv('BALTO_CULQI_PUBLIC_KEY=pk_test_BALTOFixture');
putenv('BALTO_CULQI_SECRET_KEY=sk_test_BALTOFixture');
$originalService = \Drupal::service('balto_payments.orders');
\Drupal::getContainer()->set('balto_payments.orders', $service);
$outer = $database->startTransaction();
$fixtures = [];
try {
  $variation = $entities->getStorage('commerce_product_variation')->load(1);
  $initial = \Drupal::service('vet_api.catalog')->stock($variation);
  $create = function () use ($entities, $variation, &$fixtures) {
    $item = $entities->getStorage('commerce_order_item')->create([
      'type' => 'default', 'purchased_entity' => $variation, 'quantity' => '1',
      'unit_price' => $variation->getPrice(), 'overridden_unit_price' => TRUE,
    ]);
    $item->save();
    $order = $entities->getStorage('commerce_order')->create([
      'type' => 'default', 'store_id' => 1, 'uid' => 0, 'mail' => 'fixture@example.invalid',
      'order_items' => [$item], 'state' => 'draft', 'payment_gateway' => 'culqi_automatico',
      'checkout_flow' => 'default', 'checkout_step' => 'payment', 'cart' => TRUE,
    ]);
    $order->setData('vet_customer_name', 'Cliente Prueba');
    $order->setData('vet_customer_phone', '999999999');
    $order->save();
    $fixtures[] = $order->id();
    return $order;
  };
  $order = $create();
  $remote = ['object' => 'order', 'id' => 'ord_test_BALTOFixture', 'state' => 'pending', 'amount' => CulqiOrders::cents($order->getTotalPrice()->getNumber()), 'currency_code' => 'PEN', 'order_number' => 'BALTO-' . $order->uuid()];
  $response = fn(array $data) => new Response(200, ['Content-Type' => 'application/json'], json_encode($data));
  $mock->append($response($remote));
  $prepared = NULL;
  \Drupal::service('vet_api.checkout_stock_guard')->complete($order, function () use ($service, $order, &$prepared) {
    $prepared = $service->prepare($order, 'culqi_automatico', 'test');
    return [];
  });
  $check($prepared['id'] === $remote['id'], 'Se crea orden remota con total calculado por Commerce');
  $check(\Drupal::service('vet_api.catalog')->stock($variation) === $initial - 1, 'Stock reservado antes del pago externo');
  $check($order->getState()->getId() === 'validation', 'Pedido registrado antes de abrir el proveedor');
  $checkoutManager = \Drupal::service('commerce_checkout.checkout_order_manager');
  $check($checkoutManager->getCheckoutStepId($order, 'payment') === 'payment', 'Un pedido reservado puede reabrir el pago pendiente');
  $check($checkoutManager->getCheckoutStepId($order, 'review') === 'complete', 'Un pedido reservado no vuelve a editar importes en revisión');

  $paymentStorage = $entities->getStorage('commerce_payment');
  $payments = $paymentStorage->loadByProperties(['order_id' => $order->id()]);
  $payment = reset($payments);
  $check(count($payments) === 1 && $payment->getState()->getId() === 'pending', 'Pago pendiente, sin marcarlo recibido');
  $mock->append($response($remote));
  $service->prepare($order, 'culqi_automatico', 'test');
  $check(count($paymentStorage->loadByProperties(['order_id' => $order->id()])) === 1 && count($history) === 2 && $history[1]['request']->getMethod() === 'GET', 'Recargar reutiliza la orden y no crea cobros duplicados');
  $check($service->synchronize('ord_test_Unknown', 'culqi_automatico') === 'unknown' && count($history) === 2, 'Un ID desconocido no dispara consultas ni modifica pagos');
  $mock->append($response($remote));
  $check($service->synchronize($remote['id'], 'culqi_automatico') === 'pending', 'El retorno sin pago sigue pendiente');
  $mock->append($response($remote));
  $formPayment = $paymentStorage->create(['type' => 'payment_manual', 'payment_gateway' => 'culqi_automatico', 'order_id' => $order->id(), 'amount' => $order->getTotalPrice()]);
  $formPlugin = new \Drupal\balto_payments\PluginForm\CulqiOrdersForm();
  $formPlugin->setEntity($formPayment);
  $form = $formPlugin->buildConfigurationForm(['#return_url' => 'http://localhost:8080/return', '#cancel_url' => 'http://localhost:8080/cancel'], new \Drupal\Core\Form\FormState());
  $settings = $form['#attached']['drupalSettings']['baltoCulqi'];
  $check($settings['publicKey'] === 'pk_test_BALTOFixture' && $settings['orderId'] === $remote['id'], 'El formulario recibe la llave pública y la orden preparada');
  $check(!str_contains(json_encode($form['#attached']), 'sk_test_'), 'La llave privada no llega al navegador');

  foreach ([['amount', $remote['amount'] + 100], ['currency_code', 'USD'], ['order_number', 'otro'], ['id', 'ord_test_Otro']] as [$field, $value]) {
    $bad = $remote; $bad['state'] = 'paid'; $bad[$field] = $value;
    $mock->append($response($bad));
    $reject(fn() => $service->synchronize($remote['id'], 'culqi_automatico'), 'Se rechaza confirmación con ' . $field . ' incorrecto');
  }
  $paymentStorage->resetCache();
  $check($paymentStorage->load($payment->id())->getState()->getId() === 'pending', 'Los datos inconsistentes no confirman dinero');
  $notifyGateway = clone $gateway;
  $notifyGateway->enable();
  $notifyPlugin = \Drupal::service('plugin.manager.commerce_payment_gateway')->createInstance('balto_culqi_orders', $gateway->getPluginConfiguration() + ['_entity' => $notifyGateway]);
  $mock->append($response($remote));
  $forged = Request::create('/', 'POST', [], [], [], [], json_encode(['type' => 'order.status.changed', 'data' => json_encode(['id' => $remote['id'], 'state' => 'paid', 'amount' => 1])]));
  $notifyPlugin->onNotify($forged);
  $paymentStorage->resetCache();
  $check($paymentStorage->load($payment->id())->getState()->getId() === 'pending', 'Un webhook que afirma paid no sustituye la confirmación del proveedor');
  $badWebhook = FALSE;
  try { $notifyPlugin->onNotify(Request::create('/', 'POST', [], [], [], [], '{')); }
  catch (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException) { $badWebhook = TRUE; }
  $check($badWebhook, 'Webhook malformado rechazado sin procesar pagos');

  $mock->append(new Response(503));
  $reject(fn() => $service->synchronize($remote['id'], 'culqi_automatico'), 'Una caída del proveedor no confirma el pago');
  $mock->append($response($remote + []));
  $service->synchronize($remote['id'], 'culqi_automatico');
  $paid = $remote; $paid['state'] = 'paid';
  $mock->append($response($paid));
  $check($service->synchronize($remote['id'], 'culqi_automatico') === 'paid', 'Confirmación autenticada registra el pago');
  $paymentStorage->resetCache();
  $check($paymentStorage->load($payment->id())->getState()->getId() === 'completed', 'Pago Commerce completado');
  $entities->getStorage('commerce_order')->resetCache();
  $check($entities->getStorage('commerce_order')->loadUnchanged($order->id())->isPaid(), 'El pedido refleja automáticamente el pago total');
  $calls = count($history);
  $check($service->synchronize($remote['id'], 'culqi_automatico') === 'paid' && count($history) === $calls, 'Evento duplicado es idempotente');
  $check(\Drupal::service('vet_api.catalog')->stock($variation) === $initial - 1, 'Confirmar dinero no descuenta stock dos veces');
  $expiredOrder = $create();
  $exp = $remote; $exp['id'] = 'ord_test_Expires'; $exp['order_number'] = 'BALTO-' . $expiredOrder->uuid();
  $mock->append($response($exp));
  \Drupal::service('vet_api.checkout_stock_guard')->complete($expiredOrder, function () use ($service, $expiredOrder) { $service->prepare($expiredOrder, 'culqi_automatico', 'test'); return []; });
  $exp['state'] = 'expired'; $mock->append($response($exp));
  $check($service->synchronize($exp['id'], 'culqi_automatico') === 'expired', 'Expiración verificada anula la orden de pago');
  $entities->getStorage('commerce_order')->resetCache();
  $check($entities->getStorage('commerce_order')->loadUnchanged($expiredOrder->id())->getState()->getId() === 'canceled', 'El pedido vencido se cancela automáticamente');
  $check(\Drupal::service('vet_api.catalog')->stock($variation) === $initial - 1, 'Expirar devuelve la reserva al inventario');
  $exp['state'] = 'paid'; $mock->append($response($exp));
  $check($service->synchronize($exp['id'], 'culqi_automatico') === 'canceled', 'Un aviso tardío no reactiva un pedido cancelado');

  $uncertain = $create();
  $mock->append(new Response(503));
  try {
    \Drupal::service('vet_api.checkout_stock_guard')->complete($uncertain, function () use ($service, $uncertain) {
      try { $service->prepare($uncertain, 'culqi_automatico', 'test'); }
      catch (\Drupal\commerce_payment\Exception\PaymentGatewayException) {
        // El paso nativo de Commerce transforma el error en una redirección.
        throw new \Drupal\commerce\Response\NeedsRedirectException('/checkout/' . $uncertain->id() . '/complete');
      }
      return [];
    });
  } catch (\Drupal\commerce\Response\NeedsRedirectException) {}
  $storedUncertain = $entities->getStorage('commerce_order')->loadUnchanged($uncertain->id());
  $check($storedUncertain->getData('balto_culqi_creation_started') && $storedUncertain->getState()->getId() === 'validation', 'La redirección de error conserva la operación incierta y su reserva');
  $calls = count($history);
  $reject(fn() => $service->prepare($storedUncertain, 'culqi_automatico', 'test'), 'Una creación incierta no se repite automáticamente');
  $check(count($history) === $calls, 'Sin segunda llamada de creación después del error');
  // La pasarela permanece desactivada, incluso al recibir un POST válido.
  $notify = $gateway->getPlugin()->onNotify(Request::create('/', 'POST', [], [], [], [], json_encode(['type' => 'order.status.changed', 'data' => ['id' => $remote['id'], 'state' => 'paid']])));
  $check($notify->getStatusCode() === 503, 'Webhook de pasarela desactivada no se procesa');
  $officialOnly = TRUE;
  foreach ($history as $entry) {
    $officialOnly = $officialOnly && str_starts_with((string) $entry['request']->getUri(), 'https://api.culqi.com/v2/orders');
  }
  $check($officialOnly, 'HTTP limitado al endpoint oficial de órdenes');
}
finally {
  \Drupal::getContainer()->set('balto_payments.orders', $originalService);
  $outer->rollBack();
  $entities->getStorage('commerce_order')->resetCache();
  $entities->getStorage('commerce_payment')->resetCache();
  putenv('BALTO_CULQI_PUBLIC_KEY'); putenv('BALTO_CULQI_SECRET_KEY');
}
$check(\Drupal::service('vet_api.catalog')->stock($variation) === $initial, 'Datos e inventario restaurados tras las pruebas');
$report = ['date' => gmdate('c'), 'checks' => count($results), 'results' => $results, 'real_api_calls' => 0, 'real_money' => FALSE];
file_put_contents('/tmp/balto-automatic-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo json_encode(['checks' => count($results), 'real_api_calls' => 0, 'real_money' => FALSE], JSON_PRETTY_PRINT) . "\n";
