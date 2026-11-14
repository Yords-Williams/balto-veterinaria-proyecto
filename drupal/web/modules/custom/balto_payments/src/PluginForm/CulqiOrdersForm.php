<?php

declare(strict_types=1);

namespace Drupal\balto_payments\PluginForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;

/** El SDK recibe solo la llave pública y el ID de la orden. */
final class CulqiOrdersForm extends PaymentOffsiteForm {

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $payment = $this->entity;
    $gateway = $payment->getPaymentGateway();
    $mode = $gateway->getPlugin()->getMode();
    $service = \Drupal::service('balto_payments.orders');
    [$public] = $service->credentials($mode);
    $order = $payment->getOrder();
    $remote = $service->prepare($order, $gateway->id(), $mode);
    $form['#attached']['library'][] = 'balto_payments/checkout';
    $form['#attached']['drupalSettings']['baltoCulqi'] = [
      'publicKey' => $public, 'orderId' => $remote['id'], 'amount' => $remote['amount'],
      'returnUrl' => $form['#return_url'], 'mode' => $mode,
    ];
    $form['#attributes']['class'][] = 'balto-online-payment';
    $form['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Paga tu pedido')];
    $form['description'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('Abre el pago, elige tu billetera para pagar por QR o genera un código para pagar desde la banca móvil. BALTO recibirá la confirmación del proveedor.')];
    if ($mode === 'test') {
      $form['test'] = ['#type' => 'html_tag', '#tag' => 'p', '#attributes' => ['class' => ['catalog-demo-notice']], '#value' => $this->t('Entorno de pruebas. No realices pagos con dinero real.')];
    }
    $form['open'] = ['#type' => 'html_tag', '#tag' => 'button', '#value' => $this->t('Abrir pago con Culqi'), '#attributes' => ['type' => 'button', 'class' => ['button'], 'data-balto-culqi-open' => '']];
    $form['status'] = ['#type' => 'html_tag', '#tag' => 'p', '#attributes' => ['role' => 'status', 'data-balto-culqi-status' => ''], '#value' => ''];
    $form['return'] = ['#type' => 'link', '#title' => $this->t('Ver el estado de mi pedido'), '#url' => Url::fromUri($form['#return_url']), '#attributes' => ['class' => ['text-link']]];
    $form['back'] = ['#type' => 'link', '#title' => $this->t('Seguir explorando la tienda'), '#url' => Url::fromRoute('vet_api.catalog'), '#attributes' => ['class' => ['text-link']]];
    return $form;
  }
}
