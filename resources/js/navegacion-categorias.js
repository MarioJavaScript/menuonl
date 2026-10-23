// Navegación de categorías de menu.publico (antes vivía en el script en línea de la vista).

/**
 * Navegación de categorías: marca con aria-current la píldora de la sección que está a la vista y la centra en su barra.
 */
export function marcarPildoraActiva() {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    const pills = Array.from(document.querySelectorAll('.sticky-nav a[href^="#"]'));
    const porId = new Map(pills.map((a) => [a.getAttribute('href').slice(1), a]));
    const visibles = new Set();
    const marcar = () => {
        const primero = Array.from(porId.keys()).find((id) => visibles.has(id));
        pills.forEach((a) => a.removeAttribute('aria-current'));
        if (primero) {
            const a = porId.get(primero);
            a.setAttribute('aria-current', 'true');
            const scroller = a.closest('.nav-scroller');
            if (scroller) {
                scroller.scrollTo({ left: Math.max(0, a.offsetLeft - (scroller.clientWidth / 2) + (a.offsetWidth / 2)), behavior: 'smooth' });
            }
        }
    };
    const obs = new IntersectionObserver((entradas) => {
        entradas.forEach((en) => (en.isIntersecting ? visibles.add(en.target.id) : visibles.delete(en.target.id)));
        marcar();
    }, { rootMargin: '-20% 0px -70% 0px' });
    porId.forEach((a, id) => {
        const sec = document.getElementById(id);
        if (sec) {
            obs.observe(sec);
        }
    });
}

export function iniciarNavegacionCategorias() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', marcarPildoraActiva);
    } else {
        marcarPildoraActiva();
    }
}
