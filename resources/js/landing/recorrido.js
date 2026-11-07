/**
 * Recorrido de la portada: en qué tramo del progreso (0 a 1) se ve cada capa de texto. Sin DOM, para poder probarlo.
 */

/** Tramos en orden del HTML. El primero y el último no terminan de ese lado: el hero se ve desde el inicio y los planes hasta el final. */
export const BANDAS = [
    { inicio: -Infinity, fin: 0.18 },
    { inicio: 0.2, fin: 0.4 },
    { inicio: 0.44, fin: 0.62 },
    { inicio: 0.64, fin: 0.82 },
    { inicio: 0.85, fin: Infinity },
];

/** Fracción del progreso que dura cada aparición o desaparición. */
export const FUNDIDO = 0.02;

/** Altura en pantallas que suma cada tramo. Cinco tramos: 1 + 4 × 0.5 = 3 pantallas (300vh). */
export const PANTALLAS_POR_TRAMO = 0.5;

/** Al dejar de hacer scroll, la escena frena y se detiene aquí. */
export const REPOSO_MS = 5000;

/** Tramo final de REPOSO_MS en el que la escena va frenando, para que no se congele de golpe. */
export const FRENADO_MS = 1200;

/** Pantallas angostas, verticales u horizontales muy bajas. La misma consulta está en landing.css: es composición, no capacidad. */
export const COMPOSICION_MOVIL = '(max-width: 47.5rem), (max-aspect-ratio: 9/10), (orientation: landscape) and (max-height: 31.25rem)';

/** Alto mínimo, en px, que un panel debe dejarle libre a la escena. Si no lo deja, el tramo pasa al flujo normal. */
export const ALTO_MINIMO_DE_ESCENA = 130;

/** Separación, en px, entre el panel y la escena. */
export const SEPARACION = 8;

/** Fracción del progreso que tarda la escena en pasar del área de un tramo al área común con el siguiente. */
export const TRANSICION_DE_AREA = 0.03;

/** Fracción del área libre que ocupa la escena, y lo más que se amplía. */
const RELLENO = 0.94;
const ESCALA_MAXIMA = 2;

/** Un panel más ancho que esto va arriba de la escena; si no, va a un lado. */
const ANCHO_DE_PANEL_SUPERIOR = 0.65;

export const limitar = (valor, minimo = 0, maximo = 1) => Math.min(maximo, Math.max(minimo, valor));

const suave = (t) => t * t * (3 - 2 * t);

/**
 * Si el panel ocupa casi todo el ancho, la escena va debajo de él.
 */
export function panelVaArriba(panel, anchoDeVista) {
    return panel.der - panel.izq > anchoDeVista * ANCHO_DE_PANEL_SUPERIOR;
}

/**
 * Rectángulo libre para la escena según el panel visible, en px relativos a la vista.
 */
export function areaLibre(panel, ancho, alto, inferior) {
    if (panelVaArriba(panel, ancho)) {
        return { izq: panel.izq, der: panel.der, sup: panel.inf + SEPARACION, inf: alto - inferior };
    }

    return { izq: panel.der + SEPARACION, der: ancho - panel.izq, sup: panel.sup, inf: alto - inferior };
}

function interseccion(a, b) {
    return { izq: Math.max(a.izq, b.izq), der: Math.min(a.der, b.der), sup: Math.max(a.sup, b.sup), inf: Math.min(a.inf, b.inf) };
}

function mezclar(a, b, t) {
    return {
        izq: a.izq + (b.izq - a.izq) * t,
        der: a.der + (b.der - a.der) * t,
        sup: a.sup + (b.sup - a.sup) * t,
        inf: a.inf + (b.inf - a.inf) * t,
    };
}

/**
 * Área de la escena en un punto del recorrido. Entre dos tramos se reduce al área común antes de que el primero se funda y
 * crece después de que el segundo ya se ve: así no pisa ninguno de los dos paneles.
 */
export function areaEnProgreso(progreso, areas) {
    for (let indice = 0; indice < areas.length - 1; indice += 1) {
        const sale = BANDAS[indice].fin - FUNDIDO;
        const entra = BANDAS[indice + 1].inicio + FUNDIDO;

        if (progreso < sale - TRANSICION_DE_AREA) {
            return areas[indice];
        }

        if (progreso <= entra + TRANSICION_DE_AREA) {
            const comun = interseccion(areas[indice], areas[indice + 1]);

            if (progreso < sale) {
                return mezclar(areas[indice], comun, suave((progreso - sale + TRANSICION_DE_AREA) / TRANSICION_DE_AREA));
            }

            return progreso <= entra ? comun : mezclar(comun, areas[indice + 1], suave((progreso - entra) / TRANSICION_DE_AREA));
        }
    }

    return areas[areas.length - 1];
}

/**
 * Escala y desplazamiento, en px, que centran la caja proyectada de la escena dentro del área.
 */
export function encuadre(caja, area) {
    const anchoCaja = Math.max(1, caja.der - caja.izq);
    const altoCaja = Math.max(1, caja.inf - caja.sup);
    const escala = Math.min(ESCALA_MAXIMA, (Math.max(1, area.der - area.izq) * RELLENO) / anchoCaja, (Math.max(1, area.inf - area.sup) * RELLENO) / altoCaja);

    return {
        escala,
        x: (area.izq + area.der) / 2 - (escala * (caja.izq + caja.der)) / 2,
        y: (area.sup + area.inf) / 2 - (escala * (caja.sup + caja.inf)) / 2,
    };
}

/**
 * Altura de la escena, en pantallas, para la cantidad de tramos que caben en ella.
 */
export function pantallasDeEscena(tramos) {
    return 1 + (tramos - 1) * PANTALLAS_POR_TRAMO;
}

/**
 * Progreso con el que termina la escena: si los últimos tramos no caben y pasan al flujo normal, termina antes.
 */
export function progresoFinal(tramos) {
    return tramos >= BANDAS.length ? 1 : BANDAS[tramos - 1].fin;
}

/**
 * Visibilidad (0 a 1) de un tramo. El último tramo que cabe en la escena se queda visible hasta el final.
 */
export function visibilidad(progreso, indice, tramos = BANDAS.length) {
    const { inicio, fin } = BANDAS[indice];
    const entrada = inicio === -Infinity ? 1 : limitar((progreso - inicio) / FUNDIDO);
    const salida = indice === tramos - 1 || fin === Infinity ? 1 : limitar((fin - progreso) / FUNDIDO);

    return entrada * salida;
}

/**
 * Progreso (0 a final) según el scroll, dentro de una escena con `recorrido` píxeles de scroll útil.
 */
export function progresoDesdeScroll(scroll, inicioEscena, recorrido, final) {
    return recorrido <= 0 ? 0 : limitar((scroll - inicioEscena) / recorrido) * final;
}

/**
 * Posición de scroll en la que un tramo ya se ve completo: es el destino de sus anclas y del foco con teclado.
 */
export function scrollDeTramo(indice, inicioEscena, recorrido, final) {
    const { inicio } = BANDAS[indice];
    const progreso = inicio === -Infinity ? 0 : inicio + 2 * FUNDIDO;

    return inicioEscena + (progreso / final) * recorrido;
}

/**
 * Cuántos de los tramos, en orden, caben en la altura disponible. Desde el primero que no cabe, todos pasan al flujo normal.
 */
export function tramosQueCaben(alturas, disponible) {
    const primeroQueNoCabe = alturas.findIndex((altura) => altura > disponible);

    return primeroQueNoCabe === -1 ? alturas.length : primeroQueNoCabe;
}

/**
 * Velocidad del reloj de la escena según el tiempo sin scroll: 1 en movimiento, baja a 0 al llegar al reposo.
 */
export function velocidadDelReloj(msSinScroll) {
    return limitar((REPOSO_MS - msSinScroll) / FRENADO_MS);
}
