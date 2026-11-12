<?php

/** Confirmación explícita del propietario del proyecto; no repetir en reinicios. */
if (!\Drupal::state()->get('balto.schedule.confirmed.v2')) {
  \Drupal::configFactory()->getEditable('vet_api.business')
    ->set('consultation_schedule', 'Lunes a domingo de 9:00 a. m. a 8:00 p. m.')
    ->set('schedule_is_example', FALSE)->save();
  \Drupal::state()->set('balto.schedule.confirmed.v2', TRUE);
  echo "Horario confirmado de BALTO guardado.\n";
}
