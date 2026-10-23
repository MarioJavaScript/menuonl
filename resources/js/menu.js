import Alpine from '@alpinejs/csp';
import { iniciarNavegacionCategorias } from './navegacion-categorias.js';

/*
 * JS del menú público. Se usa la compilación CSP de Alpine porque la política de seguridad
 * de la página no permite 'unsafe-eval'. El pedido por WhatsApp vive en pedido/ y se carga con menu-pedido.js.
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
            this.$dispatch('hoja-cerrar');
            this.desplazamiento = 0;
        }, sinMovimiento() ? 0 : 200);
    },
}));

/**
 * Detalle del plato en una hoja inferior. Si el navegador soporta View Transitions, la imagen de la
 * tarjeta se transforma en la imagen del detalle (y viceversa); si no, solo se anima la hoja.
 */
Alpine.data('detallePlato', () => ({
    abierto: false,
    nombre: '',
    descripcion: '',
    imagen: '',
    idPlato: '',
    boton: null,
    imagenOrigen: null,

    get raiz() {
        return document.getElementById('platoHojaFondo');
    },

    ref(nombre) {
        return this.raiz.querySelector(`[x-ref="${nombre}"]`);
    },

    get imagenSrc() {
        return this.imagen || 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    },

    clic(evento) {
        if (this.abierto && evento.target.closest('.btn-cart-add')) {
            setTimeout(() => this.cerrar(), 150);

            return;
        }

        const disparador = evento.target.closest('[data-abrir-plato], .plato-imagen');

        if (!disparador || this.raiz.contains(evento.target)) {
            return;
        }

        const articulo = disparador.closest('.plato');
        const boton = articulo ? articulo.querySelector('[data-abrir-plato]') : null;

        if (boton) {
            this.abrir(articulo, boton);
        }
    },

    abrir(articulo, boton) {
        this.boton = boton;
        this.idPlato = articulo.dataset.plato || '';
        this.nombre = boton.textContent.trim();
        this.descripcion = (articulo.querySelector('.dish-desc')?.textContent ?? '').trim();

        const imagenTarjeta = articulo.querySelector('.plato-imagen img');
        this.imagen = imagenTarjeta ? imagenTarjeta.getAttribute('src') : '';
        this.imagenOrigen = imagenTarjeta;

        this.copiar(this.ref('etiquetas'), articulo.querySelector('ul'));
        this.copiar(this.ref('compra'), articulo.querySelector('.plato-precio'), articulo.querySelector('.plato-variantes'), articulo.querySelector('.plato-pie'));

        const mostrar = () => {
            this.abierto = true;
            this.raiz.classList.remove('cerrando');
            this.raiz.classList.add('open');
            document.body.style.overflow = 'hidden';
        };

        if (imagenTarjeta && this.puedeTransicionar()) {
            const nombreVt = `plato-${this.idPlato}`;

            imagenTarjeta.style.viewTransitionName = nombreVt;
            this.raiz.classList.add('con-transicion');

            const transicion = document.startViewTransition(async () => {
                imagenTarjeta.style.viewTransitionName = '';
                mostrar();
                await this.$nextTick();
                this.ref('imagen').style.viewTransitionName = nombreVt;
            });

            transicion.finished.finally(() => {
                this.ref('imagen').style.viewTransitionName = '';
                this.raiz.classList.remove('con-transicion');
                this.enfocar();
            });

            return;
        }

        mostrar();
        this.enfocar();
    },

    cerrar() {
        if (!this.abierto) {
            return;
        }

        const ocultar = () => {
            this.abierto = false;
            this.raiz.classList.remove('open', 'cerrando');
            document.body.style.overflow = '';
            this.boton?.focus();
        };

        if (this.imagen && this.imagenOrigen && this.puedeTransicionar()) {
            const nombreVt = `plato-${this.idPlato}`;

            this.ref('imagen').style.viewTransitionName = nombreVt;
            this.raiz.classList.add('con-transicion');

            const transicion = document.startViewTransition(async () => {
                this.ref('imagen').style.viewTransitionName = '';
                ocultar();
                this.imagenOrigen.style.viewTransitionName = nombreVt;
            });

            transicion.finished.finally(() => {
                this.imagenOrigen.style.viewTransitionName = '';
                this.raiz.classList.remove('con-transicion');
            });

            return;
        }

        if (sinMovimiento()) {
            ocultar();

            return;
        }

        this.raiz.classList.add('cerrando');
        setTimeout(ocultar, 200);
    },

    cerrarFondo(evento) {
        if (evento.target === this.raiz) {
            this.cerrar();
        }
    },

    teclas(evento) {
        if (!this.abierto) {
            return;
        }

        if (evento.key === 'Escape') {
            this.cerrar();

            return;
        }

        if (evento.key !== 'Tab') {
            return;
        }

        const enfocables = [...this.raiz.querySelectorAll('button, a[href], input, textarea')].filter((el) => !el.disabled && el.offsetParent !== null);

        if (!enfocables.length) {
            return;
        }

        const primero = enfocables[0];
        const ultimo = enfocables[enfocables.length - 1];

        if (evento.shiftKey && (document.activeElement === primero || !this.raiz.contains(document.activeElement))) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && (document.activeElement === ultimo || !this.raiz.contains(document.activeElement))) {
            evento.preventDefault();
            primero.focus();
        }
    },

    copiar(destino, ...origenes) {
        destino.replaceChildren(...origenes.filter(Boolean).map((nodo) => nodo.cloneNode(true)));
    },

    enfocar() {
        this.ref('cerrar')?.focus();
    },

    puedeTransicionar() {
        return typeof document.startViewTransition === 'function' && !sinMovimiento();
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

iniciarNavegacionCategorias();

window.Alpine = Alpine;
Alpine.start();
