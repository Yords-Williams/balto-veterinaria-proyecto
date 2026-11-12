<?php

declare(strict_types=1);

namespace Drupal\vet_api\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;

#[CommerceCheckoutPane(id: 'vet_customer', label: new TranslatableMarkup('Datos para el retiro en tienda'), default_step: 'order_information', wrapper_element: 'fieldset')]
final class CustomerDetails extends CheckoutPaneBase {

  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['name'] = ['#type' => 'textfield', '#title' => $this->t('Nombre y apellidos'), '#default_value' => $this->order->getData('vet_customer_name', ''), '#required' => TRUE, '#maxlength' => 120, '#attributes' => ['autocomplete' => 'name']];
    $pane_form['phone'] = ['#type' => 'tel', '#title' => $this->t('Celular de contacto'), '#default_value' => $this->order->getData('vet_customer_phone', ''), '#required' => TRUE, '#maxlength' => 20, '#description' => $this->t('Celular peruano de nueve dígitos.'), '#attributes' => ['autocomplete' => 'tel']];
    $pane_form['pickup'] = ['#type' => 'item', '#markup' => $this->t('Retiro en el local de BALTO. Coordina disponibilidad y hora por WhatsApp; no se realiza envío.')];
    return $pane_form;
  }

  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (mb_strlen(trim($values['name'] ?? '')) < 3) {
      $form_state->setError($pane_form['name'], $this->t('Escribe tu nombre y apellidos.'));
    }
    if (!preg_match('/^9\d{8}$/', preg_replace('/\D/', '', $values['phone'] ?? ''))) {
      $form_state->setError($pane_form['phone'], $this->t('Ingresa un celular peruano de nueve dígitos que comience con 9.'));
    }
  }

  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setData('vet_customer_name', trim($values['name']));
    $this->order->setData('vet_customer_phone', preg_replace('/\D/', '', $values['phone']));

  }

  public function buildPaneSummary() {
    return ['#plain_text' => $this->order->getData('vet_customer_name', '') . ' · ' . $this->order->getData('vet_customer_phone', '') . ' · Retiro en tienda'];
  }

}
