<?php

declare(strict_types=1);

namespace Drupal\balto_payments\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use GuzzleHttp\ClientInterface;

/** Confirma dinero exclusivamente mediante consultas autenticadas a Culqi. */
final class CulqiOrders {

  public function __construct(
    private readonly ClientInterface $http,
    private readonly EntityTypeManagerInterface $entities,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly LoggerChannelFactoryInterface $loggers,
    private readonly ConfigFactoryInterface $config,
  ) {}

  public function credentials(string $mode): array {
    $public = (string) getenv('BALTO_CULQI_PUBLIC_KEY');
    $secret = (string) getenv('BALTO_CULQI_SECRET_KEY');
    if (!in_array($mode, ['test', 'live'], TRUE)
      || !preg_match('/^pk_' . $mode . '_[A-Za-z0-9]+$/D', $public)
      || !preg_match('/^sk_' . $mode . '_[A-Za-z0-9]+$/D', $secret)) {
      throw new PaymentGatewayException('Configura las llaves Culqi del mismo entorno en el servidor.');
    }
    if ($mode === 'live' && (getenv('BALTO_CULQI_ALLOW_LIVE') !== 'true' || $this->config->get('vet_api.business')->get('catalog_is_example') !== FALSE)) {
      throw new PaymentGatewayException('Los cobros reales requieren habilitación explícita e inventario real.');
    }
    return [$public, $secret];
  }

  /** Conversión decimal exacta; nunca se aceptan importes del navegador. */
  public static function cents(string $number): int {
    if (!preg_match('/^(\d{1,8})(?:\.(\d{1,6}))?$/D', $number, $matches)) {
      throw new PaymentGatewayException('Importe no válido.');
    }
    $fraction = str_pad($matches[2] ?? '', 2, '0');
    if (trim(substr($fraction, 2), '0') !== '') {
      throw new PaymentGatewayException('El importe debe tener precisión de céntimos.');
    }
    return (int) $matches[1] * 100 + (int) substr($fraction, 0, 2);
  }

  private function request(string $method, string $path, string $mode, ?array $body = NULL): array {
    [, $secret] = $this->credentials($mode);
    try {
      $options = ['headers' => ['Authorization' => 'Bearer ' . $secret, 'Accept' => 'application/json'], 'connect_timeout' => 5, 'timeout' => 20, 'allow_redirects' => FALSE];
      if ($body !== NULL) {
        $options['json'] = $body;
      }
      $response = $this->http->request($method, 'https://api.culqi.com/v2/orders' . $path, $options);
      if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
        throw new \RuntimeException('Unexpected response');
      }
      $data = json_decode((string) $response->getBody(), TRUE, 32, JSON_THROW_ON_ERROR);
      if (!is_array($data) || ($data['object'] ?? '') !== 'order') {
        throw new \RuntimeException('Unexpected object');
      }
      return $data;
    }
    catch (\Throwable $exception) {
      // No registrar cuerpos remotos, datos personales, cabeceras ni llaves.
      throw new PaymentGatewayException('No se pudo verificar el pago con Culqi. Se reintentará la consulta.');
    }
  }

  public function prepare(OrderInterface $order, string $gateway_id, string $mode): array {
    $this->credentials($mode);
    if ($order->get('payment_gateway')->target_id !== $gateway_id || $order->isPaid() || $order->getState()->getId() === 'canceled') {
      throw new PaymentGatewayException('El pedido no admite un nuevo pago.');
    }
    if ($order->getTotalPrice()->getCurrencyCode() !== 'PEN') {
      throw new PaymentGatewayException('Esta integración acepta importes en soles.');
    }
    $amount = self::cents($order->getTotalPrice()->getNumber());
    if ($amount < 600) {
      throw new PaymentGatewayException('Este medio requiere un importe mínimo de S/ 6.00.');
    }
    $existing = $this->entities->getStorage('commerce_payment')->loadByProperties(['order_id' => $order->id(), 'payment_gateway' => $gateway_id]);
    foreach ($existing as $payment) {
      if ($payment->getPaymentGatewayMode() !== $mode || self::cents($payment->getAmount()->getNumber()) !== $amount) {
        throw new PaymentGatewayException('El pedido o entorno cambió; no se generará otro cobro.');
      }
      if ($payment->getState()->getId() === 'voided') {
        throw new PaymentGatewayException('La orden de pago expiró. Inicia un pedido nuevo.');
      }
      $remote = $this->request('GET', '/' . rawurlencode($payment->getRemoteId()), $mode);
      $this->validate($remote, $amount, 'BALTO-' . $order->uuid(), $mode, $payment->getRemoteId());
      return $remote;
    }
    // Un intento interrumpido requiere conciliación antes de volver a crear una orden.
    if ($order->getData('balto_culqi_creation_started')) {
      throw new PaymentGatewayException('La creación anterior requiere conciliación. No se duplicará el cobro.');
    }
    $name = preg_split('/\s+/', trim($order->getData('vet_customer_name', '')), 2);
    $phone = $order->getData('vet_customer_phone', '');
    if (count($name) !== 2 || !preg_match('/^9\d{8}$/D', $phone) || !filter_var($order->getEmail(), FILTER_VALIDATE_EMAIL)) {
      throw new PaymentGatewayException('Completa nombre, apellidos, correo y celular antes de pagar.');
    }
    $reference = 'BALTO-' . $order->uuid();
    $order->setData('balto_culqi_creation_started', TRUE);
    $order->setData('balto_culqi_reference', $reference);
    // Persistir la reserva antes de la llamada externa: un timeout no permite
    // reiniciar el checkout creando otra orden de pago por accidente.
    if ($order->getState()->getId() === 'draft') {
      $order->getState()->applyTransitionById('place');
    }
    $order->save();
    $remote = $this->request('POST', '', $mode, [
      'amount' => $amount, 'currency_code' => 'PEN', 'description' => 'Pedido BALTO',
      'order_number' => $reference, 'expiration_date' => time() + 1800, 'confirm' => TRUE,
      'client_details' => ['first_name' => $name[0], 'last_name' => $name[1], 'email' => $order->getEmail(), 'phone_number' => '+51' . $phone],
    ]);
    $this->validate($remote, $amount, $reference, $mode);
    $payment = $this->entities->getStorage('commerce_payment')->create([
      'type' => 'payment_manual', 'payment_gateway' => $gateway_id, 'order_id' => $order->id(),
      'amount' => $order->getTotalPrice(), 'payment_gateway_mode' => $mode, 'state' => 'pending',
      'remote_id' => $remote['id'], 'remote_state' => $remote['state'],
    ]);
    $payment->save();
    $order->setData('balto_culqi_reference', $reference);
    $order->setData('balto_culqi_creation_started', FALSE);
    $order->save();
    return $remote;
  }

  public function validate(array $remote, int $amount, string $reference, string $mode, ?string $remote_id = NULL): void {
    if (($remote['object'] ?? '') !== 'order'
      || !preg_match('/^ord_' . $mode . '_[A-Za-z0-9]+$/D', $remote['id'] ?? '')
      || ($remote_id !== NULL && $remote['id'] !== $remote_id)
      || !is_int($remote['amount'] ?? NULL) || $remote['amount'] !== $amount
      || ($remote['currency_code'] ?? '') !== 'PEN' || ($remote['order_number'] ?? '') !== $reference
      || !in_array($remote['state'] ?? '', ['created', 'pending', 'paid', 'expired', 'deleted'], TRUE)) {
      throw new PaymentGatewayException('La respuesta de pago no corresponde al pedido.');
    }
  }

  /** Idempotente: un evento duplicado nunca registra dos pagos. */
  public function synchronize(string $remote_id, string $gateway_id): string {
    if (!preg_match('/^ord_(test|live)_[A-Za-z0-9]+$/D', $remote_id)) {
      throw new PaymentGatewayException('Identificador de pago no válido.');
    }
    $key = 'balto.culqi.' . $remote_id;
    if (!$this->lock->acquire($key, 45)) {
      throw new PaymentGatewayException('El pago ya se está verificando.');
    }
    $locked_order_id = NULL;
    try {
      $payments = $this->entities->getStorage('commerce_payment')->loadByProperties(['remote_id' => $remote_id, 'payment_gateway' => $gateway_id]);
      if (count($payments) !== 1) {
        return 'unknown';
      }
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = reset($payments);
      if ($payment->getState()->getId() === 'completed') {
        return 'paid';
      }
      $locked_order_id = (int) $payment->getOrderId();
      $order = $this->entities->getStorage('commerce_order')->loadForUpdate($locked_order_id);
      $remote = $this->request('GET', '/' . rawurlencode($remote_id), $payment->getPaymentGatewayMode());
      $amount = self::cents($payment->getAmount()->getNumber());
      $this->validate($remote, $amount, 'BALTO-' . $order->uuid(), $payment->getPaymentGatewayMode(), $remote_id);
      if ($order->get('payment_gateway')->target_id !== $gateway_id || $order->getTotalPrice()->getCurrencyCode() !== 'PEN' || self::cents($order->getTotalPrice()->getNumber()) !== $amount) {
        throw new PaymentGatewayException('El total o medio del pedido cambió.');
      }
      if ($order->getState()->getId() === 'canceled') {
        if ($remote['state'] === 'paid') {
          $this->loggers->get('balto_payments')->error('Pago posterior a la cancelación del pedido @id: requiere conciliación.', ['@id' => $order->id()]);
        }
        return 'canceled';
      }
      $transaction = $this->database->startTransaction();
      try {
        if ($remote['state'] === 'paid') {
          if ($order->getState()->getId() === 'draft' || $payment->getState()->getId() === 'voided' || $order->isPaid()) {
            throw new PaymentGatewayException('El pedido requiere conciliación antes de aceptar el pago.');
          }
          $payment->setRemoteState('paid');
          $payment->setState('completed');
          $payment->save();
          \Drupal::service('commerce_payment.order_updater')->updateOrder($order, TRUE);
        }
        elseif (in_array($remote['state'], ['expired', 'deleted'], TRUE) && $payment->getState()->getId() === 'pending') {
          $payment->setRemoteState($remote['state']);
          $payment->setState('voided');
          $payment->save();
          // La transición nativa cancela el pedido y devuelve las existencias.
          $transition = $order->getState()->getTransitions()['cancel'] ?? NULL;
          if ($transition) {
            $order->getState()->applyTransition($transition);
            $order->save();
          }
        }
        else {
          $payment->setRemoteState($remote['state']);
          $payment->setChangedTime(time());
          $payment->save();
        }
        $transaction->commitOrRelease();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      return $remote['state'];
    }
    finally {
      if ($locked_order_id !== NULL) {
        $this->entities->getStorage('commerce_order')->releaseLock($locked_order_id);
      }
      $this->lock->release($key);
    }
  }

  /** Reintentos de consulta sin crear cobros ni depender de n8n. */
  public function reconcile(): void {
    $ids = $this->entities->getStorage('commerce_payment')->getQuery()->accessCheck(FALSE)
      ->condition('state', 'pending')->condition('remote_id', 'ord_', 'STARTS_WITH')->sort('changed')->range(0, 20)->execute();
    foreach ($this->entities->getStorage('commerce_payment')->loadMultiple($ids) as $payment) {
      if ($payment->getPaymentGateway()?->getPluginId() !== 'balto_culqi_orders') {
        continue;
      }
      try {
        $this->synchronize($payment->getRemoteId(), $payment->getPaymentGatewayId());
      }
      catch (\Throwable) {
        // Rotar también los fallos para que un pago no bloquee toda la cola.
        $payment->setChangedTime(time());
        $payment->save();
        $this->loggers->get('balto_payments')->warning('No se pudo conciliar el pago @id; se reintentará.', ['@id' => $payment->id()]);
      }
    }
  }
}
