import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
    ALTO_MINIMO_DE_ESCENA,
    areaEnProgreso,
    areaLibre,
    BANDAS,
    COMPOSICION_MOVIL,
    encuadre,
    FUNDIDO,
    SEPARACION,
} from '../../resources/js/landing/recorrido.js';

const css = readFileSync(new URL('../../resources/css/landing.css', import.meta.url), 'utf8');

/** Contenido del bloque @media con esta consulta exacta. */
function bloqueDeMedia(consulta) {
    const inicio = css.indexOf(`@media ${consulta} {`);

    assert.notEqual(inicio, -1, `Falta @media ${consulta}`);

    let profundidad = 0;

    for (let i = css.indexOf('{', inicio); i < css.length; i += 1) {
        profundidad += css[i] === '{' ? 1 : css[i] === '}' ? -1 : 0;

        if (profundidad === 0) {
            return css.slice(inicio, i);
        }
    }

    throw new Error('Bloque sin cerrar');
}

const movil = () => bloqueDeMedia(COMPOSICION_MOVIL);

function regla(bloque, selector) {
    const inicio = bloque.indexOf(`${selector} {`);

    assert.notEqual(inicio, -1, `Falta la regla ${selector}`);

    return bloque.slice(inicio, bloque.indexOf('}', inicio));
}

const sobrepuestos = (a, b) => a.izq < b.der && b.izq < a.der && a.sup < b.inf && b.sup < a.inf;

test('CSS y JS usan la misma consulta para la composición móvil', () => {
    assert.ok(css.includes(`@media ${COMPOSICION_MOVIL} {`));
});

test('en móvil el panel es opaco: nada se ve a través del texto', () => {
    const declaracion = regla(movil(), '.landing-3d .escena .tramo__caja');

    assert.match(declaracion, /background:\s*var\(--ice\)/);
    assert.doesNotMatch(declaracion, /rgb\(|backdrop-filter/);
});

test('el panel va arriba, sin centrarse encima de la escena', () => {
    assert.match(regla(movil(), '.landing-3d .escena .tramo'), /align-items:\s*flex-start/);
});

test('la altura de la vista usa svh, para que la barra de Safari no tape contenido', () => {
    assert.match(css, /--vista:\s*100svh/);
    assert.match(css, /\.escena__vista \{[^}]*height:\s*var\(--vista\)/);
});

test('se respetan las áreas seguras', () => {
    const bloque = movil();

    for (const lado of ['top', 'left', 'right', 'bottom']) {
        assert.ok(bloque.includes(`env(safe-area-inset-${lado})`), `Falta safe-area-inset-${lado}`);
    }
});

test('en móvil se compacta el panel antes de reducir la escena, y los botones siguen siendo alcanzables', () => {
    const bloque = movil();

    assert.match(bloque, /\.enlace-demo[^{]*\{\s*display:\s*none/);
    assert.match(regla(bloque, '.landing-3d .escena .btn'), /min-height:\s*2\.75rem/);
    assert.match(regla(bloque, '.landing-3d .escena .features > li'), /padding:\s*0/);
});

test('en horizontal el panel deja libre un lado', () => {
    assert.match(regla(bloqueDeMedia('(orientation: landscape) and (max-height: 31.25rem)'), '.landing-3d .escena .tramo__caja'), /width:\s*58%/);
});

test('el enlace al menú de ejemplo no se pierde: sigue en el cierre, fuera de la escena', () => {
    const vista = readFileSync(new URL('../../resources/views/landing/inicio.blade.php', import.meta.url), 'utf8');

    assert.ok(vista.indexOf('btn--ghost" href="{{ $demoUrl }}"') > vista.indexOf('id="contacto"'));
});

test('un panel ancho deja libre lo de abajo; uno angosto, lo de al lado', () => {
    const ancho = areaLibre({ izq: 8, der: 382, sup: 77, inf: 400 }, 390, 844, 20);

    assert.deepEqual(ancho, { izq: 8, der: 382, sup: 400 + SEPARACION, inf: 824 });

    const angosto = areaLibre({ izq: 8, der: 488, sup: 61, inf: 300 }, 844, 390, 20);

    assert.deepEqual(angosto, { izq: 488 + SEPARACION, der: 836, sup: 61, inf: 370 });
});

test('con el tramo ya visible, la escena usa toda su área', () => {
    const areas = [
        { izq: 8, der: 382, sup: 400, inf: 824 },
        { izq: 8, der: 382, sup: 500, inf: 824 },
    ];

    assert.deepEqual(areaEnProgreso(0, areas), areas[0]);
    assert.deepEqual(areaEnProgreso(0.3, areas), areas[1]);
});

test('en todo el recorrido, el área nunca pisa un panel que se esté viendo', () => {
    const paneles = [
        { izq: 8, der: 382, sup: 77, inf: 390 },
        { izq: 8, der: 382, sup: 77, inf: 430 },
        { izq: 8, der: 382, sup: 77, inf: 250 },
        { izq: 8, der: 382, sup: 77, inf: 460 },
        { izq: 8, der: 382, sup: 77, inf: 610 },
    ];
    const areas = paneles.map((panel) => areaLibre(panel, 390, 844, 20));

    for (let p = 0; p <= 1; p += 0.001) {
        const area = areaEnProgreso(p, areas);

        BANDAS.forEach(({ inicio, fin }, indice) => {
            if (p > inicio && p < fin) {
                assert.ok(!sobrepuestos(area, paneles[indice]), `progreso ${p}, tramo ${indice}`);
            }
        });
    }
});

test('el cambio de área entre tramos es continuo, sin saltos', () => {
    const areas = [400, 500, 250, 460, 610].map((sup) => ({ izq: 8, der: 382, sup, inf: 824 }));
    let anterior = areaEnProgreso(0, areas).sup;

    for (let p = 0; p <= 1; p += 0.001) {
        const actual = areaEnProgreso(p, areas).sup;

        assert.ok(Math.abs(actual - anterior) < 25, `salto de ${actual - anterior} px en ${p}`);
        anterior = actual;
    }
});

test('el encuadre deja la caja completa dentro del área, sin importar su tamaño', () => {
    const area = { izq: 8, der: 382, sup: 400, inf: 824 };

    for (const caja of [{ izq: 100, der: 300, sup: 200, inf: 600 }, { izq: -200, der: 700, sup: -100, inf: 900 }, { izq: 190, der: 200, sup: 400, inf: 410 }]) {
        const { escala, x, y } = encuadre(caja, area);

        assert.ok(escala * caja.izq + x >= area.izq);
        assert.ok(escala * caja.der + x <= area.der);
        assert.ok(escala * caja.sup + y >= area.sup);
        assert.ok(escala * caja.inf + y <= area.inf);
    }
});

test('el encuadre centra la caja en el área', () => {
    const area = { izq: 8, der: 382, sup: 400, inf: 824 };
    const caja = { izq: 100, der: 300, sup: 200, inf: 600 };
    const { escala, x, y } = encuadre(caja, area);

    assert.ok(Math.abs(escala * 200 + x - 195) < 0.001);
    assert.ok(Math.abs(escala * 400 + y - 612) < 0.001);
});

test('la separación y el mínimo tienen sentido para una escena legible', () => {
    assert.ok(ALTO_MINIMO_DE_ESCENA >= 120);
    assert.ok(FUNDIDO > 0);
});
