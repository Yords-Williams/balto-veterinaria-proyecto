<?php

/** Confirmación explícita del propietario del proyecto; no repetir en reinicios. */
if (!\Drupal::state()->get('balto.schedule.confirmed.v1')) {
  \Drupal::configFactory()->getEditable('vet_api.business')
    ->set('consultation_schedule', 'Lunes a domingo de 09:00 a 20:00')
    ->set('schedule_is_example', FALSE)->save();
  \Drupal::state()->set('balto.schedule.confirmed.v1', TRUE);
  echo "Horario confirmado de BALTO guardado.\n";
}
