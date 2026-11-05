import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Pedido de la plantilla J (Mariscos): el controlador compartido con el prefijo mar y la clave menuonl_cart_j, con el mismo
// conjunto de data-mar-* que escribe resources/views/components/sitio/mariscos/pedido.blade.php y la vista del menú.
const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const CLAVE = 'menuonl_cart_j_bahia';
const HOSTIL = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';

/**
 * @param {{modalidades?: string[], marcada?: string|null, restaurante?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ modalidades = ['domicilio', 'recoger'], marcada = 'domicilio', restaurante = 'La Bahía', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        class: 'mar-mas', hidden: '', 'data-mar-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '',
        'data-nombre': platillo.nombre, 'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio,
    }));
    const sinJs = el('a', { 'data-mar-sin-js': '', href: 'https://wa.me/525512345678' });
    const fila = el('template', { 'data-mar-plantilla-linea': '' }, [el('li', { class: 'mar-linea' }, [
        el('p', { 'data-mar-nombre': '' }),
        el('p', { 'data-mar-subtotal': '' }),
        el('input', { 'data-mar-nota': '' }),
        el('button', { 'data-mar-menos': '' }),
        el('span', { 'data-mar-cantidad': '' }),
        el('button', { 'data-mar-mas-uno': '' }),
    ])]);
    const dialogo = el('dialog', { 'data-mar-dialogo': '' }, [
        el('button', { 'data-mar-cerrar': '' }),
        el('p', { 'data-mar-vacio': '' }),
        el('ul', { 'data-mar-lineas': '' }),
        fila,
        el('fieldset', { 'data-mar-entrega': '' }, modalidades.map((modalidad) => el('input', {
            type: 'radio', name: 'mar-pedido-entrega', value: modalidad, 'data-mar-modalidad': '', ...(modalidad === marcada ? { checked: '' } : {}),
        }))),
        el('textarea', { 'data-mar-cocina': '' }),
        el('span', { 'data-mar-total': '' }),
        el('p', { 'data-mar-aviso-envio': '', hidden: '' }),
        el('div', { 'data-mar-enviado': '', hidden: '' }, [el('button', { 'data-mar-listo': '' }), el('button', { 'data-mar-aun-no': '' })]),
        el('div', { 'data-mar-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-mar-conservar': '' }), el('button', { 'data-mar-confirmar': '' })]),
        el('button', { 'data-mar-enviar': '', disabled: '' }),
        el('button', { 'data-mar-vaciar': '', hidden: '' }),
    ]);
    const barra = el('button', { 'data-mar-barra': '', hidden: '' }, [el('span', {}, [el('span', { 'data-mar-cuenta': '' }), el('span', { 'data-mar-barra-total': '' })])]);
    const raiz = el('div', {
        'data-mar-pedido': '', 'data-numero': '525512345678', 'data-restaurante': restaurante, 'data-moneda': 'MXN', 'data-locale': 'es-MX',
        'data-mensaje': '', 'data-clave': CLAVE,
    }, [barra, el('p', { 'data-mar-toast': '', hidden: '' }), dialogo]);
    const entorno = montarPagina([sinJs, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, botones, sinJs, campo: (atributo) => raiz.querySelector(`[data-mar-${atributo}]`) };
}

const iniciar = () => iniciarPedido('mar', 'menuonl_cart_j');
const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

test('la entrada de la plantilla J inicia el controlador compartido con su prefijo y su clave', () => {
    const entrada = readFileSync(new URL('../../resources/js/sitio-mariscos-pedido.js', import.meta.url), 'utf8');

    assert.match(entrada, /iniciarPedido\('mar', 'menuonl_cart_j'\)/);
    assert.doesNotMatch(entrada, /innerHTML|onclick|sessionStorage/);
});

test('con JavaScript aparecen los botones "+" y se oculta el enlace sin pedido armado', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Rollo de salmón', precio: '165.00' }] });
    iniciar();

    assert.equal(entorno.botones[0].hidden, false);
    assert.equal(entorno.sinJs.hidden, true);
});

test('nombres y notas hostiles: el mensaje es texto plano limpio y localStorage guarda solo ids, cantidades y notas', () => {
    const entorno = pagina({
        restaurante: `La Bahía ${HOSTIL}`,
        platillos: [{ producto: 7, variante: 70, nombre: `Rollo ${HOSTIL}`, nombreVariante: `Grande ${HOSTIL}`, precio: '180.00' }],
    });
    iniciar();
    entorno.botones[0].clic();
    entorno.botones[0].clic();

    const nota = entorno.campo('lineas').querySelector('[data-mar-nota]');
    nota.value = `  sin *wasabi* _ni_ ~jengibre~ ${HOSTIL}\u0000 ${'x'.repeat(200)}`;
    nota.dispatchEvent({ type: 'input', target: nota });
    entorno.campo('cocina').value = `Salsa *aparte* ${HOSTIL}`;
    entorno.campo('enviar').clic();

    assert.deepEqual(Object.keys(guardado(entorno)[0]).sort(), ['c', 'n', 'p', 'v']);
    assert.equal(guardado(entorno)[0].p, 7);
    assert.equal(guardado(entorno)[0].v, 70);
    assert.equal(guardado(entorno)[0].c, 2);
    assert.ok(guardado(entorno)[0].n.length <= 80, 'La nota de la línea se recorta a 80 caracteres');
    assert.doesNotMatch(entorno.memoria.get(CLAVE), /Rollo|Grande|180|La Bahía/);

    assert.equal(entorno.abiertas.length, 1);
    const [url] = entorno.abiertas[0];
    assert.match(url, /^https:\/\/wa\.me\/525512345678\?text=/);
    const mensaje = mensajeDe(url);
    assert.match(mensaje, /\*Entrega:\* Domicilio\n/);
    assert.match(mensaje, /2x Rollo /);
    assert.doesNotMatch(mensaje, /\u0000/);
    assert.doesNotMatch(mensaje, /\*wasabi\*|_ni_|~jengibre~|\*aparte\*/);
    assert.doesNotMatch(mensaje, /Mesa/);
});

test('sin elegir Domicilio o Para recoger no se abre WhatsApp', () => {
    const entorno = pagina({ marcada: null, platillos: [{ producto: 1, nombre: 'Rollo de salmón', precio: '165.00' }] });
    iniciar();
    entorno.botones[0].clic();
    entorno.campo('enviar').clic();

    assert.equal(entorno.abiertas.length, 0);

    entorno.raiz.querySelectorAll('[data-mar-modalidad]')[1].checked = true;
    entorno.campo('enviar').clic();
    assert.equal(entorno.abiertas.length, 1);
    assert.match(mensajeDe(entorno.abiertas[0][0]), /\*Entrega:\* Para recoger\n/);
});

test('un carrito guardado con un platillo que ya no está en la página se descarta', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Rollo de salmón', precio: '165.00' }] }, {
        [CLAVE]: JSON.stringify([{ p: 99, v: null, c: 3, n: '' }, { p: 1, v: null, c: 2, n: 'sin ajonjolí' }]),
    });
    iniciar();

    assert.deepEqual(guardado(entorno), [{ p: 1, v: null, c: 2, n: 'sin ajonjolí' }]);
    assert.equal(entorno.campo('total').textContent, '$330.00');
    assert.equal(entorno.barra.hidden, false);
});
