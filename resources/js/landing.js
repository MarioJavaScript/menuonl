/**
 * Portada: controla el recorrido por tramos y carga la escena 3D después del primer pintado.
 *
 * Solo actúa si public/js/landing-criterio.js dejó la clase `landing-3d`. Cualquier fallo vuelve a la versión estática, que ya
 * es la página completa. three.js va aparte, con import() dinámico, para no descargarlo nunca en la versión estática.
 */
import {
    ALTO_MINIMO_DE_ESCENA,
    areaEnProgreso,
    areaLibre,
    COMPOSICION_MOVIL,
    pantallasDeEscena,
    panelVaArriba,
    progresoDesdeScroll,
    progresoFinal,
    REPOSO_MS,
    scrollDeTramo,
    SEPARACION,
    tramosQueCaben,
    velocidadDelReloj,
    visibilidad,
} from './landing/recorrido.js';
import {
    CUADROS_DE_MEDICION,
    CUADROS_DESCARTADOS,
    CUADROS_EN_USO,
    decidir,
    MS_MAXIMOS_DE_ARRANQUE,
    MS_PAUSA,
} from './landing/rendimiento.js';

const raiz = document.documentElement;
const SUAVIZADO = 5;

if (raiz.classList.contains('landing-3d')) {
    raiz.classList.add('landing-js');
    iniciar();
}

function iniciar() {
    const escenaEl = document.querySelector('[data-escena]');
    const vista = escenaEl.querySelector('[data-escena-vista]');
    const lienzo = escenaEl.querySelector('canvas');
    const flujo = document.querySelector('[data-escena-flujo]');
    const barra = escenaEl.querySelector('[data-escena-barra]');
    const tramos = [...vista.querySelectorAll('.tramo')];
    const eventos = new AbortController();
    const opciones = { signal: eventos.signal };

    const composicionMovil = window.matchMedia(COMPOSICION_MOVIL);

    let tramosEnEscena = tramos.length;
    let areas = null;
    let inicioEscena = 0;
    let recorrido = 0;
    let final = 1;
    let progreso = 0;
    let reloj = 0;
    let ultimoScroll = performance.now();
    let ultimoCuadro = 0;
    let cuadroPedido = 0;
    let enPantalla = true;
    let tramoActivo = 0;
    let ancho = window.innerWidth;
    let alto = window.innerHeight;
    let escena = null;
    let nivel = raiz.dataset.calidad;
    let duraciones = [];
    let descartar = 0;
    let midiendoArranque = false;
    let inicioArranque = 0;
    let terminada = false;

    /**
     * Rectángulo de cada panel en px relativos a la vista, sin el desplazamiento del fundido.
     */
    function medirPaneles() {
        const origen = vista.getBoundingClientRect();

        return tramos.map((tramo) => {
            tramo.style.removeProperty('transform');
            const rectangulo = tramo.querySelector('.tramo__caja').getBoundingClientRect();

            return { izq: rectangulo.left - origen.left, der: rectangulo.right - origen.left, sup: rectangulo.top - origen.top, inf: rectangulo.bottom - origen.top };
        });
    }

    function medir() {
        const cabecera = document.querySelector('.site-header')?.offsetHeight ?? 0;
        const scrollPrevio = window.scrollY;
        tramos.forEach((tramo) => vista.append(tramo));

        const alturas = tramos.map((tramo) => tramo.querySelector('.tramo__caja').offsetHeight);
        const anchoVista = vista.clientWidth;
        const altoVista = vista.clientHeight;
        let disponible = altoVista - cabecera - 40;

        areas = null;

        if (composicionMovil.matches) {
            const paneles = medirPaneles();
            const inferior = altoVista - (barra?.parentElement.offsetTop ?? altoVista) + SEPARACION;
            const minimoDeEscena = panelVaArriba(paneles[0], anchoVista) ? SEPARACION + ALTO_MINIMO_DE_ESCENA : 0;

            disponible = altoVista - paneles[0].sup - inferior - minimoDeEscena;
            areas = paneles.map((panel) => areaLibre(panel, anchoVista, altoVista, inferior));
        }

        tramosEnEscena = tramosQueCaben(alturas, disponible);
        areas = areas?.slice(0, tramosEnEscena) ?? null;

        if (tramosEnEscena < 2) {
            aEstatica(false);

            return;
        }

        tramos.slice(tramosEnEscena).forEach((tramo) => flujo.append(tramo));
        escenaEl.style.setProperty('--escena-pantallas', String(pantallasDeEscena(tramosEnEscena)));
        final = progresoFinal(tramosEnEscena);

        // Mientras los tramos estaban juntos en la escena, la página pudo acortarse y mover el scroll.
        if (window.scrollY !== scrollPrevio) {
            window.scrollTo({ top: scrollPrevio, behavior: 'instant' });
        }

        inicioEscena = escenaEl.getBoundingClientRect().top + window.scrollY;
        recorrido = Math.max(0, escenaEl.offsetHeight - vista.clientHeight);
    }

    function pintarTramos() {
        for (let indice = 0; indice < tramosEnEscena; indice += 1) {
            const valor = visibilidad(progreso, indice, tramosEnEscena);
            const tramo = tramos[indice];

            tramo.style.opacity = String(valor);
            // En móvil la escena va justo debajo del panel: solo se funde, sin desplazarse hacia ella.
            tramo.style.transform = valor === 1 || areas ? 'none' : `translateY(${(1 - valor) * 24}px)`;
            tramo.classList.toggle('es-activo', valor > 0.5);

            if (valor > 0.5) {
                tramoActivo = indice;
            }
        }

        if (barra) {
            barra.style.transform = `scaleX(${final > 0 ? progreso / final : 0})`;
        }
    }

    function despertar() {
        if (!cuadroPedido && !terminada && enPantalla) {
            ultimoCuadro = 0;
            cuadroPedido = requestAnimationFrame(cuadro);
        }
    }

    function aplicarMedicion() {
        const decision = decidir(duraciones, nivel);
        duraciones = [];

        if (decision === 'estatica') {
            aEstatica(true);

            return;
        }

        if (decision !== 'seguir') {
            nivel = decision;
            raiz.dataset.calidad = nivel;
            descartar = CUADROS_DESCARTADOS;
            escena.cambiarNivel(nivel);

            return;
        }

        if (midiendoArranque) {
            midiendoArranque = false;
            raiz.classList.add('escena-lista');
            ultimoScroll = performance.now();
        }
    }

    function registrarDuracion(duracion, ahora) {
        if (midiendoArranque && ahora - inicioArranque > MS_MAXIMOS_DE_ARRANQUE) {
            aEstatica(true);

            return;
        }

        if (descartar > 0) {
            descartar -= 1;

            return;
        }

        if (duracion < MS_PAUSA) {
            duraciones.push(duracion);
        }

        if (duraciones.length >= (midiendoArranque ? CUADROS_DE_MEDICION : CUADROS_EN_USO)) {
            aplicarMedicion();
        }
    }

    /**
     * Si la medición del arranque se interrumpió (otra pestaña, la escena fuera de la pantalla), vuelve a empezar: no dice nada del dispositivo.
     */
    function reiniciarMedicionDeArranque() {
        if (midiendoArranque) {
            inicioArranque = performance.now();
            duraciones = [];
            descartar = CUADROS_DESCARTADOS;
        }
    }

    function cuadro(ahora) {
        cuadroPedido = 0;

        const duracion = ultimoCuadro ? ahora - ultimoCuadro : 0;
        const segundos = Math.min(0.05, duracion / 1000);
        ultimoCuadro = ahora;

        if (midiendoArranque) {
            ultimoScroll = ahora;
        }

        const msSinScroll = ahora - ultimoScroll;
        const destino = progresoDesdeScroll(window.scrollY, inicioEscena, recorrido, final);

        progreso += (destino - progreso) * (1 - Math.exp(-segundos * SUAVIZADO));

        if (Math.abs(destino - progreso) < 0.0002) {
            progreso = destino;
        }

        reloj += segundos * velocidadDelReloj(msSinScroll);
        pintarTramos();

        if (escena) {
            try {
                escena.dibujar(progreso, reloj, areas ? areaEnProgreso(progreso, areas) : null);
            } catch {
                aEstatica(true);

                return;
            }

            if (duracion > 0) {
                registrarDuracion(duracion, ahora);
            }
        }

        if (terminada) {
            return;
        }

        const sigueEnMovimiento = msSinScroll < REPOSO_MS || progreso !== destino;

        if (enPantalla && sigueEnMovimiento) {
            cuadroPedido = requestAnimationFrame(cuadro);
        }
    }

    /**
     * Vuelve a la versión estática sin mover al visitante de la sección que estaba viendo.
     *
     * @param {boolean} porRendimiento Si es por lentitud o error, no se reintenta en esta sesión.
     */
    function aEstatica(porRendimiento) {
        if (terminada) {
            return;
        }

        terminada = true;
        cancelAnimationFrame(cuadroPedido);
        eventos.abort();
        escena?.destruir();

        if (porRendimiento) {
            try {
                sessionStorage.setItem('landing-estatica', '1');
            } catch {
                // Sin almacenamiento solo se repite la medición en la siguiente página.
            }
        }

        const seccion = tramos[tramoActivo];
        const estabaAvanzado = window.scrollY > 0;

        tramos.forEach((tramo) => {
            vista.append(tramo);
            tramo.style.removeProperty('opacity');
            tramo.style.removeProperty('transform');
            tramo.classList.remove('es-activo');
        });
        escenaEl.style.removeProperty('--escena-pantallas');
        raiz.classList.remove('landing-3d', 'escena-lista');
        raiz.removeAttribute('data-calidad');

        if (estabaAvanzado) {
            seccion.scrollIntoView({ behavior: 'instant' });
        }
    }

    function irATramo(tramo, comportamiento) {
        const indice = tramos.indexOf(tramo);

        if (indice === -1 || indice >= tramosEnEscena) {
            return false;
        }

        window.scrollTo({ top: scrollDeTramo(indice, inicioEscena, recorrido, final), behavior: comportamiento });

        return true;
    }

    /**
     * Salta a un tramo sin esperar el suavizado: lo que recibe el foco o abre la dirección debe verse de inmediato.
     */
    function saltarATramo(tramo) {
        if (!irATramo(tramo, 'instant')) {
            tramo.scrollIntoView({ behavior: 'instant' });
        }

        progreso = progresoDesdeScroll(window.scrollY, inicioEscena, recorrido, final);
        pintarTramos();
        ultimoScroll = performance.now();
        despertar();
    }

    function tramoDelEnlace(enlace) {
        const destino = new URL(enlace.href, location.href);

        if (destino.pathname !== location.pathname || destino.hash === '') {
            return null;
        }

        return tramos.find((tramo) => `#${tramo.id}` === destino.hash) ?? null;
    }

    function cargarEscena() {
        if (terminada) {
            return;
        }

        import('./landing/escena.js')
            .then(({ crearEscena }) =>
                crearEscena({
                    lienzo,
                    nivel,
                    alPerderContexto: () => aEstatica(true),
                }),
            )
            .then((creada) => {
                if (terminada) {
                    creada.destruir();

                    return;
                }

                escena = creada;
                midiendoArranque = true;
                inicioArranque = performance.now();
                descartar = CUADROS_DESCARTADOS;
                despertar();
            })
            .catch(() => aEstatica(true));
    }

    medir();

    if (terminada) {
        return;
    }

    progreso = progresoDesdeScroll(window.scrollY, inicioEscena, recorrido, final);
    pintarTramos();

    // Con la fuente real los paneles cambian de alto, y con ellos el encuadre y los tramos que caben.
    document.fonts?.ready.then(() => {
        if (!terminada) {
            medir();
            ultimoScroll = performance.now();
            despertar();
        }
    });

    window.addEventListener('scroll', () => {
        ultimoScroll = performance.now();
        despertar();
    }, { passive: true, signal: eventos.signal });

    window.addEventListener('resize', () => {
        const soloCambioLaBarraDelNavegador = window.innerWidth === ancho && Math.abs(window.innerHeight - alto) < 120;

        if (soloCambioLaBarraDelNavegador) {
            return;
        }

        ancho = window.innerWidth;
        alto = window.innerHeight;
        medir();

        if (!terminada) {
            escena?.redimensionar();
            ultimoScroll = performance.now();
            despertar();
        }
    }, opciones);

    document.addEventListener('click', (evento) => {
        const enlace = evento.target.closest?.('a[href*="#"]');
        const tramo = enlace ? tramoDelEnlace(enlace) : null;

        if (tramo && irATramo(tramo, 'smooth')) {
            evento.preventDefault();
            history.pushState(null, '', `#${tramo.id}`);
        }
    }, opciones);

    window.addEventListener('popstate', () => {
        const tramo = tramos.find((candidato) => `#${candidato.id}` === location.hash);

        if (tramo) {
            saltarATramo(tramo);
        }
    }, opciones);

    vista.addEventListener('focusin', (evento) => {
        const tramo = evento.target.closest('.tramo');

        if (tramo && evento.target.matches(':focus-visible') && tramos.indexOf(tramo) !== tramoActivo) {
            saltarATramo(tramo);
        }
    }, opciones);

    window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (cambio) => {
        if (cambio.matches) {
            aEstatica(false);
        }
    }, opciones);

    new IntersectionObserver(([entrada]) => {
        enPantalla = entrada.isIntersecting;

        if (enPantalla) {
            reiniciarMedicionDeArranque();
            ultimoScroll = performance.now();
            despertar();
        }
    }).observe(escenaEl);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            reiniciarMedicionDeArranque();
            despertar();
        }
    }, opciones);

    const tramoInicial = tramos.find((tramo) => `#${tramo.id}` === location.hash);

    if (tramoInicial) {
        saltarATramo(tramoInicial);

        // El navegador repite su propio salto al fragmento al terminar de cargar, hacia la posición apilada del tramo.
        window.addEventListener('load', () => requestAnimationFrame(() => saltarATramo(tramoInicial)), { once: true, signal: eventos.signal });
    }

    despertar();

    const alPintar = () => setTimeout(cargarEscena, 0);

    if (document.readyState === 'complete') {
        requestAnimationFrame(alPintar);
    } else {
        window.addEventListener('load', () => requestAnimationFrame(alPintar), { once: true, signal: eventos.signal });
    }
}
