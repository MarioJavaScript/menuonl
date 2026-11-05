// Menú de la plantilla J (Mariscos): marca con aria-current la categoría que está a la vista. Sin dependencias.
// La barra de categorías son enlaces ancla: sin JavaScript (o sin IntersectionObserver) siguen llevando a cada sección.
const barra = document.querySelector('[data-mar-categorias]');

if (barra && 'IntersectionObserver' in window) {
    const enlaces = Array.from(barra.querySelectorAll('a[href^="#"]'));
    const porId = new Map(enlaces.map((enlace) => [enlace.getAttribute('href').slice(1), enlace]));
    const visibles = new Set();
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const marcar = () => {
        const actual = Array.from(porId.keys()).find((id) => visibles.has(id));

        enlaces.forEach((enlace) => enlace.removeAttribute('aria-current'));

        if (actual) {
            const enlace = porId.get(actual);

            enlace.setAttribute('aria-current', 'true');
            barra.scrollTo({ left: Math.max(0, enlace.offsetLeft - (barra.clientWidth / 2) + (enlace.offsetWidth / 2)), behavior: sinMovimiento ? 'auto' : 'smooth' });
        }
    };

    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => (entrada.isIntersecting ? visibles.add(entrada.target.id) : visibles.delete(entrada.target.id)));
        marcar();
    }, { rootMargin: '-20% 0px -70% 0px' });

    porId.forEach((_enlace, id) => {
        const seccion = document.getElementById(id);

        if (seccion) {
            observador.observe(seccion);
        }
    });
}
