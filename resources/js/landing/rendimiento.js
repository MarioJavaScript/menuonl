/**
 * Medición de cuadros por segundo de la escena y la decisión que sale de ella. Sin DOM, para poder probarlo.
 */

/** Del mejor al más simple. */
export const NIVELES = ['alta', 'simple', 'minima'];

/** Cuadros que se miden al arrancar, después de descartar los primeros (compilan los shaders). */
export const CUADROS_DE_MEDICION = 45;
export const CUADROS_DESCARTADOS = 5;

/** Cuadros que se miden durante el uso, ya con la escena visible. */
export const CUADROS_EN_USO = 120;

/** Hasta aquí (unos 36 fps) se sigue igual. 30 fps exactos, como el modo de bajo consumo del iPhone, bajan un nivel pero no apagan el 3D. */
export const MS_PARA_SEGUIR = 28;

/** Arriba de esto (menos de unos 22 fps) en el nivel más simple, se pasa a la versión estática. */
export const MS_PARA_APAGAR = 45;

/** Un cuadro más largo que esto es una pausa (otra pestaña, el navegador ocupado), no una medida del dispositivo. */
export const MS_PAUSA = 250;

/** Tiempo máximo del arranque, de pedir la escena a tenerla medida. */
export const MS_MAXIMOS_DE_ARRANQUE = 8000;

export function percentil75(duraciones) {
    const ordenadas = [...duraciones].sort((a, b) => a - b);

    return ordenadas.length === 0 ? 0 : ordenadas[Math.ceil(ordenadas.length * 0.75) - 1];
}

/**
 * @return {'seguir'|'simple'|'minima'|'estatica'} Qué hacer con la escena, según las duraciones medidas y su nivel actual.
 */
export function decidir(duraciones, nivel) {
    const p75 = percentil75(duraciones);

    if (p75 <= MS_PARA_SEGUIR) {
        return 'seguir';
    }

    if (p75 > MS_PARA_APAGAR) {
        return nivel === 'minima' ? 'estatica' : 'minima';
    }

    return nivel === 'alta' ? 'simple' : nivel === 'simple' ? 'minima' : 'seguir';
}
