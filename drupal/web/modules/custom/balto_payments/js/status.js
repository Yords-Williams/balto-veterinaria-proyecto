/** Actualiza la confirmación cuando el servidor recibe el pago o su vencimiento. */
(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.baltoPaymentStatus = {
    attach(context) {
      once('balto-payment-status', '[data-balto-payment-state="pending"]', context).forEach(() => {
        const deadline = Date.now() + 35 * 60 * 1000;
        const check = async () => {
          if (Date.now() > deadline) return;
          if (!document.hidden) {
            try {
              const response = await fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'text/html'}});
              if (response.ok && new URL(response.url).origin === window.location.origin) {
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const state = page.querySelector('[data-balto-payment-state]')?.getAttribute('data-balto-payment-state');
                if (state && state !== 'pending') {
                  window.location.reload();
                  return;
                }
              }
            } catch (_) { /* La siguiente consulta reintentará sin alterar el pago. */ }
          }
          window.setTimeout(check, 15000);
        };
        window.setTimeout(check, 15000);
      });
    },
  };
})(Drupal, once);
