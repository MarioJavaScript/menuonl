/*
 * Apariencia antes de pintar: misma clave que el panel (light, dark o system). Sin preferencia guardada se ve en claro.
 * Vive en un archivo propio (y no en línea) para que la política de contenido pueda prohibir scripts en línea.
 * Se carga sin defer en el <head> de x-layout.publico para aplicar el modo antes del primer pintado.
 */
(function () {
    var clave = 'filament:theme:mode';
    function aplicar(modo) {
        if (modo === 'light' || modo === 'dark' || modo === 'system') {
            document.documentElement.setAttribute('data-apariencia', modo);
        } else {
            document.documentElement.removeAttribute('data-apariencia');
        }
    }
    var guardado = null;
    try { guardado = localStorage.getItem(clave); } catch (e) {}
    aplicar(guardado);
    document.addEventListener('change', function (evento) {
        var opcion = evento.target;
        if (!opcion || !opcion.matches || !opcion.matches('[data-apariencia-opcion]')) {
            return;
        }
        aplicar(opcion.value);
        try { localStorage.setItem(clave, opcion.value); } catch (e) {}
        document.querySelectorAll('[data-apariencia-opcion]').forEach(function (otra) {
            otra.checked = otra.value === opcion.value;
        });
    });
    document.addEventListener('DOMContentLoaded', function () {
        var actual = guardado === 'dark' || guardado === 'system' ? guardado : 'light';
        document.querySelectorAll('[data-apariencia-opcion]').forEach(function (opcion) {
            opcion.checked = opcion.value === actual;
        });
    });
})();
