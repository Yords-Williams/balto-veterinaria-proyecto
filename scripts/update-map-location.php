<?php

/** Ficha del negocio con el mismo teléfono; conserva las direcciones del usuario.
 * Fuente: https://mivet.net.pe/p/veterinaria-balto-juliaca (enlace Google Maps).
 */
if (!\Drupal::state()->get('balto.map.business.v1')) {
  \Drupal::configFactory()->getEditable('vet_api.business')
    ->set('maps_cid', '9761007982775567582')->save();
  \Drupal::state()->set('balto.map.business.v1', TRUE);
  echo "Mapa asociado a la ficha de Veterinaria BALTO Juliaca.\n";
}
