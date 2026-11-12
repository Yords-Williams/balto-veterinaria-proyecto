/** Carrusel sin dependencias: pausa por foco, pestaña oculta y movimiento reducido. */
(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.baltoHeroCarousel = {
    attach(context) {
      once('balto-hero-carousel', '[data-hero-carousel]', context).forEach((root) => {
        const slides = [...root.querySelectorAll('[data-hero-slide]')];
        const dots = [...root.querySelectorAll('[data-carousel-dot]')];
        const controls = root.querySelector('[data-carousel-controls]');
        const pause = root.querySelector('[data-carousel-pause]');
        const status = root.querySelector('[data-carousel-status]');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let index = 0, timer, paused = motion.matches, hovered = false;
        const schedule = () => {
          window.clearTimeout(timer);
          if (!paused && !hovered && !document.hidden && !motion.matches) timer = window.setTimeout(() => show(index + 1, false), 8000);
        };
        const updatePause = () => {
          pause.setAttribute('aria-label', paused ? 'Reanudar carrusel' : 'Pausar carrusel');
          pause.innerHTML = paused ? '▶ <span>Reanudar</span>' : 'Ⅱ <span>Pausar</span>';
        };
        const show = (next, announce = true) => {
          index = (next + slides.length) % slides.length;
          slides.forEach((slide, i) => {
            slide.setAttribute('aria-hidden', String(i !== index));
            slide.inert = i !== index;
          });
          dots.forEach((dot, i) => {
            if (i === index) dot.setAttribute('aria-current', 'true');
            else dot.removeAttribute('aria-current');
          });
          if (announce) status.textContent = slides[index].getAttribute('aria-label');
          schedule();
        };
        root.querySelector('[data-carousel-prev]').addEventListener('click', () => show(index - 1));
        root.querySelector('[data-carousel-next]').addEventListener('click', () => show(index + 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
        pause.addEventListener('click', () => { paused = !paused; updatePause(); schedule(); });
        root.addEventListener('mouseenter', () => { hovered = true; schedule(); });
        root.addEventListener('mouseleave', () => { hovered = false; schedule(); });
        root.addEventListener('focusin', () => { paused = true; updatePause(); schedule(); });
        root.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            event.preventDefault(); show(index + (event.key === 'ArrowRight' ? 1 : -1));
          }
        });
        document.addEventListener('visibilitychange', schedule);
        motion.addEventListener('change', () => { paused = motion.matches; updatePause(); schedule(); });
        controls.hidden = false;
        updatePause(); schedule();
      });
    },
  };
})(Drupal, once);
