{{-- Apariencia antes de pintar: misma clave que el panel de Filament (light, dark o system). Lo usan x-apariencia y sus copias. --}}
<script>
    (function () {
        var clave = 'filament:theme:mode';
        function aplicar(modo) {
            if (modo === 'light' || modo === 'dark') {
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
            var actual = guardado === 'light' || guardado === 'dark' ? guardado : 'system';
            document.querySelectorAll('[data-apariencia-opcion]').forEach(function (opcion) {
                opcion.checked = opcion.value === actual;
            });
        });
    })();
</script>
