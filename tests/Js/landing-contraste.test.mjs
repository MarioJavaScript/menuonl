import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

// Contraste 4.5:1 con los tokens reales de resources/css/landing.css.
const css = readFileSync(new URL('../../resources/css/landing.css', import.meta.url), 'utf8');

function token(nombre) {
    const hallado = css.match(new RegExp(`--${nombre}:\\s*(#[0-9a-fA-F]{6})`));

    assert.ok(hallado, `Falta el token --${nombre}`);

    return hallado[1];
}

const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
const lineal = (c) => (c / 255 <= 0.03928 ? c / 255 / 12.92 : ((c / 255 + 0.055) / 1.055) ** 2.4);
const luminancia = ([r, g, b]) => 0.2126 * lineal(r) + 0.7152 * lineal(g) + 0.0722 * lineal(b);
const contraste = (a, b) => {
    const [x, y] = [luminancia(a), luminancia(b)];

    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
};
const mezclar = (frente, alfa, fondo) => frente.map((v, i) => v * alfa + fondo[i] * (1 - alfa));

function opacidadDelPanel() {
    const hallado = css.match(/background:\s*rgb\(234 246 255 \/ ([0-9.]+)\)/);

    assert.ok(hallado, 'Falta el panel de los tramos.');

    return Number(hallado[1]);
}

test('sobre el panel de cada tramo, el peor caso posible detrás (negro) sigue dando 4.5:1', () => {
    const panel = mezclar(rgb(token('ice')), opacidadDelPanel(), [0, 0, 0]);

    for (const nombre of ['navy', 'ink-soft', 'link']) {
        const valor = contraste(rgb(token(nombre)), panel);

        assert.ok(valor >= 4.5, `--${nombre} sobre el panel: ${valor.toFixed(2)}`);
    }
});

test('los textos sobre fondos sólidos llegan a 4.5:1', () => {
    const parejas = [
        ['link', 'white', '.pill y .hl sobre blanco'],
        ['link', 'ice', '.hl sobre hielo'],
        ['ink-soft', 'ice', '.sub sobre hielo'],
        ['ink-soft', 'white', 'texto de las tarjetas'],
        ['navy', 'white', 'títulos y botón secundario'],
        ['white', 'navy', 'botón principal'],
        ['on-dark-soft', 'navy', 'cierre y pie'],
    ];

    for (const [frente, fondo, uso] of parejas) {
        const valor = contraste(rgb(token(frente)), rgb(token(fondo)));

        assert.ok(valor >= 4.5, `${uso}: ${valor.toFixed(2)}`);
    }
});

test('el azul de marca y el azul bebé no se usan como color de texto', () => {
    const declaraciones = [...css.matchAll(/(?<![-\w])color:\s*var\(--(blue|sky)\)/g)];

    assert.deepEqual(declaraciones.map((d) => d[0]), []);
});
