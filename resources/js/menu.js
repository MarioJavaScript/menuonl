import Alpine from '@alpinejs/csp';

/*
 * JS del menú público. Se usa la compilación CSP de Alpine porque la política de seguridad
 * de la página no permite 'unsafe-eval'. Todo lo demás (carrito, WhatsApp) va en línea en la vista.
 */
const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Hoja inferior arrastrable: se cierra al soltar pasado el 30% de su alto o con un gesto rápido.
 */
Alpine.data('hoja', () => ({
    desplazamiento: 0,
    arrastrando: false,
    inicioY: 0,
    inicioT: 0,

    get estilo() {
        if (!this.arrastrando && this.desplazamiento === 0) {
            return '';
        }

        const transicion = this.arrastrando ? 'none' : 'transform 0.2s ease';

        return `transform: translate3d(0, ${this.desplazamiento}px, 0); transition: ${transicion};`;
    },

    empezar(evento) {
        if (evento.target.closest('button, a, input, textarea') || evento.button > 0) {
            return;
        }

        this.arrastrando = true;
        this.inicioY = evento.clientY;
        this.inicioT = evento.timeStamp;
        evento.currentTarget.setPointerCapture(evento.pointerId);
    },

    mover(evento) {
        if (!this.arrastrando) {
            return;
        }

        this.desplazamiento = Math.max(0, evento.clientY - this.inicioY);
    },

    soltar(evento) {
        if (!this.arrastrando) {
            return;
        }

        this.arrastrando = false;

        const alto = this.$root.offsetHeight || 1;
        const velocidad = this.desplazamiento / Math.max(1, evento.timeStamp - this.inicioT);

        if (this.desplazamiento > alto * 0.3 || velocidad > 0.6) {
            this.cerrarArrastrado(alto);

            return;
        }

        this.desplazamiento = 0;
    },

    cerrarArrastrado(alto) {
        this.desplazamiento = alto;
        setTimeout(() => {
            window.closeCartModal(null, true);
            this.desplazamiento = 0;
        }, sinMovimiento() ? 0 : 200);
    },
}));

/**
 * Cabecera dinámica: cuando el hero sale de la pantalla se muestra la barra compacta
 * (logo y nombre reducidos) y la navegación de categorías baja a ocupar su lugar.
 */
Alpine.data('cabecera', () => ({
    iniciar() {
        const centinela = this.$root.querySelector('[data-centinela]');

        if (!centinela || !('IntersectionObserver' in window)) {
            return;
        }

        new IntersectionObserver(([entrada]) => {
            const compacta = !entrada.isIntersecting && entrada.boundingClientRect.top < 0;

            this.$root.classList.toggle('cabecera-compacta', compacta);
        }).observe(centinela);
    },
}));

window.Alpine = Alpine;
Alpine.start();
