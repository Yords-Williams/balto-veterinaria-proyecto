/** Keep catalog filters compact on small screens; native details keeps keyboard support. */
(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.baltoCatalogFilters = {
    attach(context) {
      once('balto-catalog-filters', '.catalog-filter-disclosure', context).forEach((panel) => {
        const desktop = window.matchMedia('(min-width: 901px)');
        const summary = panel.querySelector('summary');
        const update = () => { panel.open = desktop.matches; };
        update();
        desktop.addEventListener('change', update);
        summary.addEventListener('click', (event) => {
          if (desktop.matches) event.preventDefault();
        });
      });
    },
  };
})(Drupal, once);
