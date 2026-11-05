import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Pedido de la plantilla I (Japonés): el controlador compartido con el prefijo jap y la clave menuonl_cart_i, con el mismo
// conjunto de data-jap-* que escribe resources/views/components/sitio/japones/pedido.blade.php y la vista del menú.
const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const CLAVE = 'menuonl_cart_i_kaze';
const HOSTIL = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';

/**
 * @param {{modalidades?: string[], marcada?: string|null, restaurante?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ modalidades = ['domicilio', 'recoger'], marcada = 'domicilio', restaurante = 'Kaze', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        class: 'jap-mas', hidden: '', 'data-jap-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '',
        'data-nombre': platillo.nombre, 'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio,
    }));
    const sinJs = el('a', { 'data-jap-sin-js': '', href: 'https://wa.me/525512345678' });
    const fila = el('template', { 'data-jap-plantilla-linea': '' }, [el('li', { class: 'jap-linea' }, [
        el('p', { 'data-jap-nombre': '' }),
        el('p', { 'data-jap-subtotal': '' }),
        el('input', { 'data-jap-nota': '' }),
        el('button', { 'data-jap-menos': '' }),
        el('span', { 'data-jap-cantidad': '' }),
        el('button', { 'data-jap-mas-uno': '' }),
    ])]);
    const dialogo = el('dialog', { 'data-jap-dialogo': '' }, [
        el('button', { 'data-jap-cerrar': '' }),
        el('p', { 'data-jap-vacio': '' }),
        el('ul', { 'data-jap-lineas': '' }),
        fila,
        el('fieldset', { 'data-jap-entrega': '' }, modalidades.map((modalidad) => el('input', {
            type: 'radio', name: 'jap-pedido-entrega', value: modalidad, 'data-jap-modalidad': '', ...(modalidad === marcada ? { checked: '' } : {}),
        }))),
        el('textarea', { 'data-jap-cocina': '' }),
        el('span', { 'data-jap-total': '' }),
        el('p', { 'data-jap-aviso-envio': '', hidden: '' }),
        el('div', { 'data-jap-enviado': '', hidden: '' }, [el('button', { 'data-jap-listo': '' }), el('button', { 'data-jap-aun-no': '' })]),
        el('div', { 'data-jap-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-jap-conservar': '' }), el('button', { 'data-jap-confirmar': '' })]),
        el('button', { 'data-jap-enviar': '', disabled: '' }),
        el('button', { 'data-jap-vaciar': '', hidden: '' }),
    ]);
    const barra = el('button', { 'data-jap-barra': '', hidden: '' }, [el('span', {}, [el('span', { 'data-jap-cuenta': '' }), el('span', { 'data-jap-barra-total': '' })])]);
    const raiz = el('div', {
        'data-jap-pedido': '', 'data-numero': '525512345678', 'data-restaurante': restaurante, 'data-moneda': 'MXN', 'data-locale': 'es-MX',
        'data-mensaje': '', 'data-clave': CLAVE,
    }, [barra, el('p', { 'data-jap-toast': '', hidden: '' }), dialogo]);
    const entorno = montarPagina([sinJs, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, botones, sinJs, campo: (atributo) => raiz.querySelector(`[data-jap-${atributo}]`) };
}

const iniciar = () => iniciarPedido('jap', 'menuonl_cart_i');
const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

test('la entrada de la plantilla I inicia el controlador compartido con su prefijo y su clave', () => {
    const entrada = readFileSync(new URL('../../resources/js/sitio-japones-pedido.js', import.meta.url), 'utf8');

    assert.match(entrada, /iniciarPedido\('jap', 'menuonl_cart_i'\)/);
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
        restaurante: `Kaze ${HOSTIL}`,
        platillos: [{ producto: 7, variante: 70, nombre: `Rollo ${HOSTIL}`, nombreVariante: `Grande ${HOSTIL}`, precio: '180.00' }],
    });
    iniciar();
    entorno.botones[0].clic();
    entorno.botones[0].clic();

    const nota = entorno.campo('lineas').querySelector('[data-jap-nota]');
    nota.value = `  sin *wasabi* _ni_ ~jengibre~ ${HOSTIL}\u0000 ${'x'.repeat(200)}`;
    nota.dispatchEvent({ type: 'input', target: nota });
    entorno.campo('cocina').value = `Salsa *aparte* ${HOSTIL}`;
    entorno.campo('enviar').clic();

    assert.deepEqual(Object.keys(guardado(entorno)[0]).sort(), ['c', 'n', 'p', 'v']);
    assert.equal(guardado(entorno)[0].p, 7);
    assert.equal(guardado(entorno)[0].v, 70);
    assert.equal(guardado(entorno)[0].c, 2);
    assert.ok(guardado(entorno)[0].n.length <= 80, 'La nota de la línea se recorta a 80 caracteres');
    assert.doesNotMatch(entorno.memoria.get(CLAVE), /Rollo|Grande|180|Kaze/);

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

    entorno.raiz.querySelectorAll('[data-jap-modalidad]')[1].checked = true;
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
