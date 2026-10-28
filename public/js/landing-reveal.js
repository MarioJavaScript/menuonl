/* Revelado al hacer scroll. Solo cambia opacity y transform (en el CSS).
   Sin movimiento reducido ni IntersectionObserver, no hace nada y todo se ve. */
(function () {
  var root = document.documentElement;
  if (!('IntersectionObserver' in window)) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  root.classList.add('reveal-ready');

  document.addEventListener('DOMContentLoaded', function () {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    document.querySelectorAll('[data-reveal]').forEach(function (el) { io.observe(el); });

    // La red de puntos del fondo se pausa cuando no está en pantalla.
    var ambient = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) { entry.target.classList.toggle('is-paused', !entry.isIntersecting); });
    });
    document.querySelectorAll('.constellation').forEach(function (el) { ambient.observe(el); });
  });
})();
