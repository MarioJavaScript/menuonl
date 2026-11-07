/* Decide, antes del primer pintado, si la portada intenta el modo 3D. Por defecto la página es estática y completa.
   No decide por ancho de pantalla, sino por capacidad. La medición de cuadros por segundo viene después, en landing.js.
   iOS y Firefox no informan deviceMemory (y Safari limita hardwareConcurrency): ahí decide solo la medición de cuadros. */
(function () {
  var raiz = document.documentElement;
  var TIEMPO_MAX_MODULO_MS = 4000;

  function pide(consulta) {
    return !!window.matchMedia && window.matchMedia(consulta).matches;
  }

  function marcadoComoLento() {
    try {
      return window.sessionStorage.getItem('landing-estatica') === '1';
    } catch (error) {
      return false;
    }
  }

  function redSuficiente(conexion) {
    if (!conexion) return true;
    if (conexion.saveData) return false;
    return conexion.effectiveType !== 'slow-2g' && conexion.effectiveType !== '2g';
  }

  function equipoSuficiente(memoria, nucleos) {
    if (typeof memoria !== 'number') return true;
    if (memoria < 2) return false;
    return typeof nucleos !== 'number' || nucleos >= 4;
  }

  function webglSuficiente() {
    try {
      var lienzo = document.createElement('canvas');
      var contexto = lienzo.getContext('webgl2', { failIfMajorPerformanceCaveat: true });
      if (!contexto) return false;
      var liberar = contexto.getExtension('WEBGL_lose_context');
      if (liberar) liberar.loseContext();
      return true;
    } catch (error) {
      return false;
    }
  }

  function calidadInicial(memoria, nucleos) {
    if (pide('(pointer: coarse)')) return 'simple';
    if (typeof memoria === 'number' && memoria < 4) return 'simple';
    if (typeof memoria === 'number' && typeof nucleos === 'number' && nucleos < 4) return 'simple';
    return 'alta';
  }

  var memoria = navigator.deviceMemory;
  var nucleos = navigator.hardwareConcurrency;

  if (pide('(prefers-reduced-motion: reduce)')) return;
  if (marcadoComoLento()) return;
  if (!redSuficiente(navigator.connection)) return;
  if (!equipoSuficiente(memoria, nucleos)) return;
  if (!webglSuficiente()) return;

  raiz.setAttribute('data-calidad', calidadInicial(memoria, nucleos));
  raiz.classList.add('landing-3d');

  // Si landing.js no llega a tiempo, la página vuelve a su versión estática y no queda con los tramos sin control.
  window.setTimeout(function () {
    if (!raiz.classList.contains('landing-js')) {
      raiz.classList.remove('landing-3d');
      raiz.removeAttribute('data-calidad');
    }
  }, TIEMPO_MAX_MODULO_MS);
})();
