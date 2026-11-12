<?php

declare(strict_types=1);

namespace Drupal\vet_api\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;

/** Solo se muestra tras el control de acceso al checkout nativo de Commerce. */
#[CommerceCheckoutPane(id: 'vet_order_confirmation', label: new TranslatableMarkup('Confirmación y coordinación del pedido'), default_step: 'complete', wrapper_element: 'container')]
final class OrderConfirmation extends CheckoutPaneBase {

  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $business = \Drupal::service('vet_api.business')->getBusiness();
    $gateway_id = $this->order->get('payment_gateway')->target_id;
    $simulated = $gateway_id === 'pago_simulado';
    $cancelled = $this->order->getState()->getId() === 'canceled';
    $number = $this->order->getOrderNumber();
    $total = $this->order->getTotalPrice();
    $formatted_total = \Drupal::service('commerce_price.currency_formatter')->format($total->getNumber(), $total->getCurrencyCode());
    $pane_form['#attributes']['class'][] = 'order-confirmation';
    $pane_form['#cache']['max-age'] = 0;
    $pane_form['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $simulated ? $this->t('¡Pedido de demostración registrado!') : $this->t('¡Recibimos tu solicitud de pedido!')];
    $pane_form['number'] = ['#plain_text' => $this->t('Pedido @number · Total @total', ['@number' => $number, '@total' => $formatted_total])];
    $pane_form['status'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $cancelled ? $this->t('Pedido cancelado. No realices pagos para este pedido.') : ($simulated ? $this->t('Pago simulado – No se realiza ningún cobro real.') : ($this->order->isPaid() ? $this->t('Pago registrado por el establecimiento. Coordina el retiro con BALTO.') : $this->t('Pendiente de confirmación y pago. BALTO debe confirmar la disponibilidad y el total antes de que pagues.')))];
    if (!$simulated && !$cancelled) {
      $method = $gateway_id === 'yape_coordinado' ? 'Yape coordinado por WhatsApp' : 'Pagar al recoger';
      $pane_form['method'] = ['#type' => 'item', '#title' => $this->t('Medio elegido'), 'value' => ['#plain_text' => $method]];
      $instructions = $gateway_id === 'yape_coordinado'
        ? $this->t('Escríbenos primero. Después de confirmar tu pedido, te indicaremos cómo pagar por Yape. El pago se verificará en la aplicación del negocio; enviar una captura no lo confirma automáticamente.')
        : $this->t('Coordina la disponibilidad y la hora de retiro por WhatsApp. Pagarás en el local cuando recojas tu pedido.');
      $pane_form['instructions'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $instructions];
      $pane_form['pickup'] = ['#type' => 'item', '#title' => $this->t('Retiro en tienda'), 'value' => ['#plain_text' => ($business['addresses'][0] ?? '') . '. ' . ($business['consultation_schedule'] ?? '')]];
      // Solo información del pedido; no añadir correo, teléfono ni datos sensibles.
      $message = 'Hola, BALTO. Quisiera confirmar el pedido ' . $number . '. Total: ' . $formatted_total . '. Medio elegido: ' . $method . '. Retiro en tienda.';
      foreach ($this->order->getItems() as $index => $item) {
        if ($index >= 12) {
          $message .= "\nY otros productos del pedido.";
          break;
        }
        $message .= "\n" . $item->getQuantity() . ' × ' . mb_substr($item->getTitle(), 0, 100);
      }
      $phone = preg_replace('/\D/', '', $business['whatsapp'] ?? '');
      if (preg_match('/^9\d{8}$/', $phone)) {
        $pane_form['whatsapp'] = ['#type' => 'link', '#title' => $this->t('Coordinar pedido por WhatsApp'), '#url' => Url::fromUri('https://wa.me/51' . $phone, ['query' => ['text' => $message]]), '#attributes' => ['class' => ['button', 'order-whatsapp'], 'target' => '_blank', 'rel' => 'noopener noreferrer']];
      }
      if (!empty($business['catalog_is_example'])) {
        $pane_form['example'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->t('El catálogo actual tiene productos, precios y existencias de ejemplo. No pagues basándote en esos importes; espera la confirmación de BALTO.')];
      }
    }
    return $pane_form;
  }

}
