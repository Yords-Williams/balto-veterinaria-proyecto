/** Menú accesible y reutilizable con la API de comportamientos de Drupal. */
(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.baltoNavigation = {
    attach(context) {
      once('balto-navigation', '.menu-toggle', context).forEach((button) => {
        const navigation = document.getElementById(button.getAttribute('aria-controls'));
        if (!navigation) return;
        const setOpen = (open) => {
          button.setAttribute('aria-expanded', String(open));
          button.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
          navigation.classList.toggle('is-open', open);
        };
        button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
        navigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            button.focus();
          }
        });
        document.addEventListener('click', (event) => {
          if (!navigation.contains(event.target) && !button.contains(event.target)) setOpen(false);
        });
        window.matchMedia('(min-width: 1100px)').addEventListener('change', () => setOpen(false));
      });
    },
  };
})(Drupal, once);
