import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// El observador de píldoras vivía en el script en línea de menu.publico y pasó a navegacion-categorias.js (cargado por menu.js).
const { marcarPildoraActiva, iniciarNavegacionCategorias } = await import('../../resources/js/navegacion-categorias.js');

function pagina() {
    const enlaces = ['destacados', 'cat-1', 'cat-2'].map((id) => Object.assign(new Nodo('a', { href: `#${id}` }), { offsetLeft: 300, offsetWidth: 80 }));
    const desplazamientos = [];
    const contenedor = Object.assign(new Nodo('div', { class: 'nav-scroller' }, enlaces), { clientWidth: 360, scrollTo: (opciones) => desplazamientos.push(opciones) });
    const nav = new Nodo('nav', { class: 'sticky-nav' }, [contenedor]);
    const secciones = ['destacados', 'cat-1', 'cat-2'].map((id) => new Nodo('section', { id }));
    const entorno = montarPagina([nav, ...secciones]);
    const observadas = [];
    let notificar = null;

    globalThis.window.IntersectionObserver = globalThis.IntersectionObserver = class {
        constructor(funcion) {
            notificar = funcion;
        }

        observe(nodo) {
            observadas.push(nodo.attrs.get('id'));
        }
    };

    return { enlaces, desplazamientos, observadas, secciones, entorno, ver: (...ids) => notificar(ids.map((id) => ({ isIntersecting: true, target: { id } }))), ocultar: (...ids) => notificar(ids.map((id) => ({ isIntersecting: false, target: { id } }))) };
}

test('observa cada sección enlazada y marca con aria-current la primera visible, en el orden de la navegación', () => {
    const { enlaces, observadas, ver, ocultar, desplazamientos } = pagina();
    marcarPildoraActiva();

    assert.deepEqual(observadas, ['destacados', 'cat-1', 'cat-2']);

    ver('cat-2');
    assert.deepEqual(enlaces.map((a) => a.getAttribute('aria-current')), [null, null, 'true']);

    ver('cat-1');
    assert.deepEqual(enlaces.map((a) => a.getAttribute('aria-current')), [null, 'true', null], 'gana la primera de la navegación aunque haya dos visibles');

    ocultar('cat-1', 'cat-2');
    assert.deepEqual(enlaces.map((a) => a.getAttribute('aria-current')), [null, null, null]);
    assert.equal(desplazamientos.length, 2);
    assert.deepEqual(desplazamientos[0], { left: 300 - 180 + 40, behavior: 'smooth' });
});

test('sin IntersectionObserver no hace nada ni falla', () => {
    pagina();
    delete globalThis.IntersectionObserver;
    delete globalThis.window.IntersectionObserver;

    assert.doesNotThrow(() => marcarPildoraActiva());
});

test('se inicia en el acto si el documento ya cargó, y al cargar si todavía no', () => {
    const { entorno } = pagina();

    globalThis.document.readyState = 'loading';
    iniciarNavegacionCategorias();
    assert.equal(entorno.documento.listeners.get('DOMContentLoaded').length, 1);

    const otra = pagina();
    globalThis.document.readyState = 'complete';
    iniciarNavegacionCategorias();
    assert.deepEqual(otra.observadas, ['destacados', 'cat-1', 'cat-2']);
});
