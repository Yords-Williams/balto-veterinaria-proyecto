/** SDK oficial: el callback del navegador nunca da un pago por confirmado. */
(function (Drupal, once, drupalSettings) {
  'use strict';
  Drupal.behaviors.baltoCulqi = {
    attach(context) {
      once('balto-culqi', '[data-balto-culqi-open]', context).forEach(button => {
        const settings = drupalSettings.baltoCulqi;
        const status = document.querySelector('[data-balto-culqi-status]');
        if (!settings || typeof window.CulqiCheckout !== 'function') {
          button.disabled = true;
          status.textContent = 'No se pudo cargar el pago. Actualiza la página o vuelve a intentarlo más tarde.';
          return;
        }
        const methods = { tarjeta: false, yape: false, billetera: settings.amount <= 50000, bancaMovil: true, agente: true, cuotealo: false };
        const checkout = new window.CulqiCheckout(settings.publicKey, {
          settings: { title: 'BALTO Veterinaria', currency: 'PEN', amount: settings.amount, order: settings.orderId },
          options: { lang: 'es', modal: true, installments: false, paymentMethods: methods, paymentMethodsSort: Object.keys(methods) },
        });
        checkout.culqi = () => {
          if (checkout.order) {
            checkout.close();
            // El servidor consultará Culqi; no se envían estados del navegador.
            window.location.assign(settings.returnUrl);
          } else {
            status.textContent = 'No se completó la operación. Puedes volver a abrir el pago.';
          }
        };
        button.addEventListener('click', () => {
          status.textContent = settings.mode === 'test' ? 'Abriendo el entorno de pruebas…' : 'Abriendo el pago…';
          checkout.open();
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
