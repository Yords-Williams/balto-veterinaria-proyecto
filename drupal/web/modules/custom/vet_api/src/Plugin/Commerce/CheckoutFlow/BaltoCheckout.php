<?php

declare(strict_types=1);

namespace Drupal\vet_api\Plugin\Commerce\CheckoutFlow;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutFlow;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;

#[CommerceCheckoutFlow(id: 'vet_checkout', label: new TranslatableMarkup('Pedidos BALTO con retiro en tienda'))]
final class BaltoCheckout extends MultistepDefault {

  public function getStepId($requested_step_id = NULL) {
    $order = $this->getOrder();
    // El pedido ya reserva stock, pero conserva acceso al widget pendiente.
    if ($requested_step_id === 'payment' && $order?->getData('balto_culqi_reference')
      && !$order->getData('balto_culqi_creation_started') && !$order->isPaid()
      && $order->getState()->getId() === 'validation'
      && $order->get('payment_gateway')->entity?->getPluginId() === 'balto_culqi_orders') {
      return 'payment';
    }
    return parent::getStepId($requested_step_id);
  }

  public function getSteps() {
    $steps = parent::getSteps();
    $steps['order_information']['label'] = $this->t('Tus datos');
    $steps['review']['label'] = $this->t('Revisa tu pedido');
    $steps['review']['next_label'] = $this->t('Revisar pedido');
    $steps['payment']['next_label'] = $this->getOrder()?->get('payment_gateway')->target_id === 'pago_simulado'
      ? $this->t('Confirmar pedido simulado') : $this->t('Confirmar pedido y coordinar retiro');
    if ($this->getOrder()?->get('payment_gateway')->entity?->getPluginId() === 'balto_culqi_orders') {
      $steps['payment']['next_label'] = $this->t('Continuar al pago');
    }
    $steps['complete']['label'] = $this->t('Pedido registrado');
    return $steps;
  }

  public function buildForm(array $form, FormStateInterface $form_state, $step_id = NULL) {
    $this->protectPreparedPayment($step_id);
    if ($step_id === 'payment' && $this->getOrder()->getState()->getId() === 'draft') {
      return \Drupal::service('vet_api.checkout_stock_guard')->complete(
        $this->getOrder(),
        fn(): array => parent::buildForm($form, $form_state, $step_id),
      );
    }
    return parent::buildForm($form, $form_state, $step_id);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->protectPreparedPayment($form['#step_id']);
    // Los pedidos gratuitos saltan el paso de pago: proteger también ese caso.
    if ($form['#step_id'] === 'review' && $this->getNextStepId('review') === 'complete') {
      \Drupal::service('vet_api.checkout_stock_guard')->complete($this->getOrder(), function () use (&$form, $form_state): array {
        parent::submitForm($form, $form_state);
        return [];
      });
      return;
    }
    parent::submitForm($form, $form_state);
  }

  /** Una orden externa activa conserva importes y medio hasta su conciliación. */
  private function protectPreparedPayment(?string $step): void {
    if (in_array($step, ['payment', 'complete'], TRUE) || !$this->getOrder()?->id()) {
      return;
    }
    $stored = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($this->getOrder()->id());
    if ($stored->getData('balto_culqi_reference') && $stored->getState()->getId() !== 'draft') {
      throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', ['commerce_order' => $stored->id(), 'step' => 'complete'])->toString());
    }
  }

}
