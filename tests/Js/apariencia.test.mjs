import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

// public/js/apariencia.js: sin preferencia guardada no marca ningún modo (la hoja lo trata como claro); light, dark y system se conservan.
const codigo = readFileSync(new URL('../../public/js/apariencia.js', import.meta.url), 'utf8');

function cargar(guardado) {
    const atributos = new Map();
    const opciones = ['system', 'light', 'dark'].map((value) => ({ value, checked: false, matches: () => true }));
    const escuchas = {};
    const document = {
        documentElement: { setAttribute: (k, v) => atributos.set(k, v), removeAttribute: (k) => atributos.delete(k) },
        addEventListener: (nombre, funcion) => {
            escuchas[nombre] = funcion;
        },
        querySelectorAll: () => opciones,
    };
    const almacen = new Map(guardado === undefined ? [] : [['filament:theme:mode', guardado]]);
    const localStorage = { getItem: (k) => (almacen.has(k) ? almacen.get(k) : null), setItem: (k, v) => almacen.set(k, v) };
    runInNewContext(codigo, { document, localStorage });
    escuchas.DOMContentLoaded();

    return { atributos, opciones, almacen, escuchas };
}

test('sin preferencia guardada no hay atributo y queda marcado Claro', () => {
    const { atributos, opciones } = cargar();
    assert.equal(atributos.has('data-apariencia'), false);
    assert.deepEqual(opciones.filter((o) => o.checked).map((o) => o.value), ['light']);
});

test('una preferencia guardada en oscuro sigue oscura', () => {
    const { atributos, opciones } = cargar('dark');
    assert.equal(atributos.get('data-apariencia'), 'dark');
    assert.deepEqual(opciones.filter((o) => o.checked).map((o) => o.value), ['dark']);
});

test('Automático guardado se conserva como system y Claro como light', () => {
    assert.equal(cargar('system').atributos.get('data-apariencia'), 'system');
    assert.equal(cargar('light').atributos.get('data-apariencia'), 'light');
});

test('un valor desconocido se trata como sin preferencia', () => {
    const { atributos } = cargar('rojo');
    assert.equal(atributos.has('data-apariencia'), false);
});

test('elegir una opción la aplica y la guarda con la misma clave', () => {
    const { atributos, almacen, escuchas, opciones } = cargar();
    escuchas.change({ target: opciones[2] });
    assert.equal(atributos.get('data-apariencia'), 'dark');
    assert.equal(almacen.get('filament:theme:mode'), 'dark');
});
