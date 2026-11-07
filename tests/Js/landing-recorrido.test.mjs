import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    BANDAS,
    FUNDIDO,
    FRENADO_MS,
    pantallasDeEscena,
    progresoDesdeScroll,
    progresoFinal,
    REPOSO_MS,
    scrollDeTramo,
    tramosQueCaben,
    velocidadDelReloj,
    visibilidad,
} from '../../resources/js/landing/recorrido.js';
import { CUADROS_DE_MEDICION, decidir, MS_PARA_APAGAR, MS_PARA_SEGUIR, percentil75 } from '../../resources/js/landing/rendimiento.js';

const iguales = (cantidad, valor) => Array.from({ length: cantidad }, () => valor);

test('con los cinco tramos, la escena mide 300vh y ningún caso la alarga', () => {
    assert.equal(pantallasDeEscena(5), 3);
    assert.equal(pantallasDeEscena(4), 2.5);
    assert.equal(pantallasDeEscena(2), 1.5);
});

test('cada tramo intermedio se ve completo al menos 25vh de scroll', () => {
    const recorridoEnVh = (pantallasDeEscena(5) - 1) * 100;

    BANDAS.slice(1, -1).forEach(({ inicio, fin }) => {
        assert.ok((fin - inicio - 2 * FUNDIDO) * recorridoEnVh >= 25, `tramo ${inicio}-${fin}`);
    });
});

test('en cualquier punto del recorrido no se ven dos tramos a la vez', () => {
    for (let p = 0; p <= 1; p += 0.002) {
        const visibles = BANDAS.filter((_, indice) => visibilidad(p, indice) > 0);

        assert.ok(visibles.length <= 1, `progreso ${p}`);
    }
});

test('el hero se ve desde el inicio y los planes hasta el final', () => {
    assert.equal(visibilidad(0, 0), 1);
    assert.equal(visibilidad(1, 4), 1);
    assert.equal(visibilidad(0.5, 0), 0);
    assert.equal(visibilidad(0, 4), 0);
});

test('si los planes pasan al flujo normal, el último tramo de la escena se queda visible', () => {
    const final = progresoFinal(4);

    assert.equal(final, BANDAS[3].fin);
    assert.equal(visibilidad(final, 3, 4), 1);
});

test('el ancla de cada tramo cae donde ese tramo ya se ve completo', () => {
    [1, 2, 3, 4].forEach((indice) => {
        const scroll = scrollDeTramo(indice, 100, 2000, 1);
        const progreso = progresoDesdeScroll(scroll, 100, 2000, 1);

        assert.equal(visibilidad(progreso, indice), 1, `tramo ${indice}`);
    });
    assert.equal(scrollDeTramo(0, 100, 2000, 1), 100);
});

test('el progreso se limita a la escena: el pie y las preguntas no alteran la animación', () => {
    assert.equal(progresoDesdeScroll(-50, 0, 1000, 1), 0);
    assert.equal(progresoDesdeScroll(5000, 0, 1000, 1), 1);
    assert.equal(progresoDesdeScroll(5000, 0, 1000, 0.82), 0.82);
    assert.equal(progresoDesdeScroll(100, 0, 0, 1), 0);
});

test('desde el primer tramo que no cabe, todos pasan al flujo normal', () => {
    assert.equal(tramosQueCaben([400, 300, 200, 500, 600], 700), 5);
    assert.equal(tramosQueCaben([400, 300, 200, 500, 900], 700), 4);
    assert.equal(tramosQueCaben([400, 800, 200, 500, 300], 700), 1);
});

test('la escena se detiene a los 5 s sin scroll, frenando al final', () => {
    assert.equal(REPOSO_MS, 5000);
    assert.equal(velocidadDelReloj(0), 1);
    assert.equal(velocidadDelReloj(REPOSO_MS - FRENADO_MS), 1);
    assert.equal(velocidadDelReloj(REPOSO_MS), 0);
    assert.equal(velocidadDelReloj(REPOSO_MS + 1000), 0);
    assert.ok(velocidadDelReloj(REPOSO_MS - FRENADO_MS / 2) > 0 && velocidadDelReloj(REPOSO_MS - FRENADO_MS / 2) < 1);
});

test('percentil 75: ignora el desorden y una pausa suelta no cambia el resultado', () => {
    assert.equal(percentil75([10, 40, 20, 30]), 30);
    assert.equal(percentil75([...iguales(44, 16), 200]), 16);
    assert.equal(percentil75([]), 0);
});

test('fluido: se queda en su nivel', () => {
    assert.equal(decidir(iguales(CUADROS_DE_MEDICION, 16), 'alta'), 'seguir');
    assert.equal(decidir(iguales(CUADROS_DE_MEDICION, MS_PARA_SEGUIR), 'simple'), 'seguir');
});

test('30 fps (bajo consumo del iPhone) baja un nivel, pero nunca apaga el 3D', () => {
    assert.equal(decidir(iguales(CUADROS_DE_MEDICION, 33.4), 'alta'), 'simple');
    assert.equal(decidir(iguales(CUADROS_DE_MEDICION, 33.4), 'simple'), 'minima');
    assert.equal(decidir(iguales(CUADROS_DE_MEDICION, 33.4), 'minima'), 'seguir');
});

test('muy lento: salta al nivel mínimo y, si ahí sigue lento, va a la versión estática', () => {
    const lento = iguales(CUADROS_DE_MEDICION, MS_PARA_APAGAR + 1);

    assert.equal(decidir(lento, 'alta'), 'minima');
    assert.equal(decidir(lento, 'simple'), 'minima');
    assert.equal(decidir(lento, 'minima'), 'estatica');
});
