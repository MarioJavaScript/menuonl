import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

// public/js/landing-criterio.js: decide por capacidad, nunca por ancho de pantalla, si la portada intenta el modo 3D.
const codigo = readFileSync(new URL('../../public/js/landing-criterio.js', import.meta.url), 'utf8');

function evaluar({ reducido = false, tactil = false, webgl = true, memoria, nucleos, conexion, marcado = false, almacenRoto = false, ancho = 390 } = {}) {
    const clases = new Set();
    const atributos = new Map();
    const temporizadores = [];
    let opcionesDeContexto = null;

    const documentElement = {
        classList: { add: (c) => clases.add(c), remove: (c) => clases.delete(c), contains: (c) => clases.has(c) },
        setAttribute: (k, v) => atributos.set(k, v),
        removeAttribute: (k) => atributos.delete(k),
    };
    const document = {
        documentElement,
        createElement: () => ({
            getContext: (_tipo, opciones) => {
                opcionesDeContexto = opciones;

                return webgl ? { getExtension: () => ({ loseContext() {} }) } : null;
            },
        }),
    };
    const consultas = { '(prefers-reduced-motion: reduce)': reducido, '(pointer: coarse)': tactil };
    const window = {
        innerWidth: ancho,
        matchMedia: (consulta) => ({ matches: consultas[consulta] ?? false }),
        sessionStorage: {
            getItem: () => {
                if (almacenRoto) {
                    throw new Error('sin acceso');
                }

                return marcado ? '1' : null;
            },
        },
        setTimeout: (funcion) => temporizadores.push(funcion),
    };

    runInNewContext(codigo, { window, document, navigator: { deviceMemory: memoria, hardwareConcurrency: nucleos, connection: conexion } });

    return { clases, atributos, temporizadores, opcionesDeContexto, documentElement };
}

test('un celular capaz usa el 3D en calidad simple, aunque la pantalla sea chica', () => {
    const r = evaluar({ tactil: true, memoria: 4, nucleos: 8, ancho: 360 });

    assert.ok(r.clases.has('landing-3d'));
    assert.equal(r.atributos.get('data-calidad'), 'simple');
});

test('una computadora capaz usa calidad alta', () => {
    const r = evaluar({ memoria: 8, nucleos: 8, ancho: 1366 });

    assert.ok(r.clases.has('landing-3d'));
    assert.equal(r.atributos.get('data-calidad'), 'alta');
});

test('sin deviceMemory (iPhone, Firefox) no se juzga por núcleos: decide la medición de cuadros', () => {
    const r = evaluar({ tactil: true, nucleos: 2 });

    assert.ok(r.clases.has('landing-3d'));
    assert.equal(r.atributos.get('data-calidad'), 'simple');
});

test('poca memoria o pocos núcleos en un dispositivo que informa su memoria quedan en estática', () => {
    assert.ok(!evaluar({ memoria: 1, nucleos: 8 }).clases.has('landing-3d'));
    assert.ok(!evaluar({ memoria: 4, nucleos: 2 }).clases.has('landing-3d'));
});

test('memoria justa (2 GB) intenta el 3D en calidad simple', () => {
    assert.equal(evaluar({ memoria: 2, nucleos: 4 }).atributos.get('data-calidad'), 'simple');
});

test('prefers-reduced-motion deja todo estático y ni siquiera prueba WebGL', () => {
    const r = evaluar({ reducido: true, memoria: 8, nucleos: 8 });

    assert.ok(!r.clases.has('landing-3d'));
    assert.equal(r.opcionesDeContexto, null);
});

test('sin WebGL queda en estática, y WebGL se pide rechazando el dibujado por software', () => {
    assert.ok(!evaluar({ webgl: false }).clases.has('landing-3d'));
    assert.equal(evaluar().opcionesDeContexto.failIfMajorPerformanceCaveat, true);
});

test('ahorro de datos o red 2G quedan en estática; 3G no se excluye', () => {
    assert.ok(!evaluar({ conexion: { saveData: true } }).clases.has('landing-3d'));
    assert.ok(!evaluar({ conexion: { effectiveType: '2g' } }).clases.has('landing-3d'));
    assert.ok(!evaluar({ conexion: { effectiveType: 'slow-2g' } }).clases.has('landing-3d'));
    assert.ok(evaluar({ conexion: { effectiveType: '3g' } }).clases.has('landing-3d'));
});

test('un dispositivo marcado como lento en esta sesión no reintenta', () => {
    assert.ok(!evaluar({ marcado: true }).clases.has('landing-3d'));
});

test('un sessionStorage que falla no rompe la decisión', () => {
    assert.ok(evaluar({ almacenRoto: true }).clases.has('landing-3d'));
});

test('si landing.js no llega a tiempo, la página vuelve a estática', () => {
    const r = evaluar();

    r.temporizadores[0]();
    assert.ok(!r.clases.has('landing-3d'));
    assert.equal(r.atributos.has('data-calidad'), false);
});

test('si landing.js llegó, el temporizador no deshace el modo 3D', () => {
    const r = evaluar();

    r.documentElement.classList.add('landing-js');
    r.temporizadores[0]();
    assert.ok(r.clases.has('landing-3d'));
});
