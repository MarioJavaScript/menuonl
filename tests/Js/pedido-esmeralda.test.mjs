import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Pedido de la plantilla L (Negro y esmeralda): el controlador compartido con el prefijo esm y la clave menuonl_cart_l, con el mismo
// conjunto de data-esm-* que escribe resources/views/components/sitio/esmeralda/pedido.blade.php y la vista del menú.
const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const CLAVE = 'menuonl_cart_l_bahia';
const HOSTIL = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';

/**
 * @param {{modalidades?: string[], marcada?: string|null, restaurante?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ modalidades = ['domicilio', 'recoger'], marcada = 'domicilio', restaurante = 'La Bahía', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        class: 'esm-mas', hidden: '', 'data-esm-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '',
        'data-nombre': platillo.nombre, 'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio,
    }));
    const sinJs = el('a', { 'data-esm-sin-js': '', href: 'https://wa.me/525512345678' });
    const fila = el('template', { 'data-esm-plantilla-linea': '' }, [el('li', { class: 'esm-linea' }, [
        el('p', { 'data-esm-nombre': '' }),
        el('p', { 'data-esm-subtotal': '' }),
        el('input', { 'data-esm-nota': '' }),
        el('button', { 'data-esm-menos': '' }),
        el('span', { 'data-esm-cantidad': '' }),
        el('button', { 'data-esm-mas-uno': '' }),
    ])]);
    const dialogo = el('dialog', { 'data-esm-dialogo': '' }, [
        el('button', { 'data-esm-cerrar': '' }),
        el('p', { 'data-esm-vacio': '' }),
        el('ul', { 'data-esm-lineas': '' }),
        fila,
        el('fieldset', { 'data-esm-entrega': '' }, modalidades.map((modalidad) => el('input', {
            type: 'radio', name: 'esm-pedido-entrega', value: modalidad, 'data-esm-modalidad': '', ...(modalidad === marcada ? { checked: '' } : {}),
        }))),
        el('textarea', { 'data-esm-cocina': '' }),
        el('span', { 'data-esm-total': '' }),
        el('p', { 'data-esm-aviso-envio': '', hidden: '' }),
        el('div', { 'data-esm-enviado': '', hidden: '' }, [el('button', { 'data-esm-listo': '' }), el('button', { 'data-esm-aun-no': '' })]),
        el('div', { 'data-esm-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-esm-conservar': '' }), el('button', { 'data-esm-confirmar': '' })]),
        el('button', { 'data-esm-enviar': '', disabled: '' }),
        el('button', { 'data-esm-vaciar': '', hidden: '' }),
    ]);
    const barra = el('button', { 'data-esm-barra': '', hidden: '' }, [el('span', {}, [el('span', { 'data-esm-cuenta': '' }), el('span', { 'data-esm-barra-total': '' })])]);
    const raiz = el('div', {
        'data-esm-pedido': '', 'data-numero': '525512345678', 'data-restaurante': restaurante, 'data-moneda': 'MXN', 'data-locale': 'es-MX',
        'data-mensaje': '', 'data-clave': CLAVE,
    }, [barra, el('p', { 'data-esm-toast': '', hidden: '' }), dialogo]);
    const entorno = montarPagina([sinJs, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, botones, sinJs, campo: (atributo) => raiz.querySelector(`[data-esm-${atributo}]`) };
}

const iniciar = () => iniciarPedido('esm', 'menuonl_cart_l');
const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

test('la entrada de la plantilla L inicia el controlador compartido con su prefijo y su clave', () => {
    const entrada = readFileSync(new URL('../../resources/js/sitio-esmeralda-pedido.js', import.meta.url), 'utf8');

    assert.match(entrada, /iniciarPedido\('esm', 'menuonl_cart_l'\)/);
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

    const nota = entorno.campo('lineas').querySelector('[data-esm-nota]');
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

    entorno.raiz.querySelectorAll('[data-esm-modalidad]')[1].checked = true;
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
