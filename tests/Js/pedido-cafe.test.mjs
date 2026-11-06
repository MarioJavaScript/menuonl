import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Pedido de la plantilla P (Cafetería): el controlador compartido con el prefijo caf y la clave menuonl_cart_caf, con el mismo
// conjunto de data-caf-* que escribe resources/views/components/sitio/cafe/pedido.blade.php y la vista del menú.
const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const CLAVE = 'menuonl_cart_caf_bahia';
const HOSTIL = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';

/**
 * @param {{modalidades?: string[], marcada?: string|null, restaurante?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ modalidades = ['domicilio', 'recoger'], marcada = 'domicilio', restaurante = 'La Bahía', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        class: 'caf-mas', hidden: '', 'data-caf-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '',
        'data-nombre': platillo.nombre, 'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio,
    }));
    const sinJs = el('a', { 'data-caf-sin-js': '', href: 'https://wa.me/525512345678' });
    const fila = el('template', { 'data-caf-plantilla-linea': '' }, [el('li', { class: 'caf-linea' }, [
        el('p', { 'data-caf-nombre': '' }),
        el('p', { 'data-caf-subtotal': '' }),
        el('input', { 'data-caf-nota': '' }),
        el('button', { 'data-caf-menos': '' }),
        el('span', { 'data-caf-cantidad': '' }),
        el('button', { 'data-caf-mas-uno': '' }),
    ])]);
    const dialogo = el('dialog', { 'data-caf-dialogo': '' }, [
        el('button', { 'data-caf-cerrar': '' }),
        el('p', { 'data-caf-vacio': '' }),
        el('ul', { 'data-caf-lineas': '' }),
        fila,
        el('fieldset', { 'data-caf-entrega': '' }, modalidades.map((modalidad) => el('input', {
            type: 'radio', name: 'caf-pedido-entrega', value: modalidad, 'data-caf-modalidad': '', ...(modalidad === marcada ? { checked: '' } : {}),
        }))),
        el('textarea', { 'data-caf-cocina': '' }),
        el('span', { 'data-caf-total': '' }),
        el('p', { 'data-caf-aviso-envio': '', hidden: '' }),
        el('div', { 'data-caf-enviado': '', hidden: '' }, [el('button', { 'data-caf-listo': '' }), el('button', { 'data-caf-aun-no': '' })]),
        el('div', { 'data-caf-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-caf-conservar': '' }), el('button', { 'data-caf-confirmar': '' })]),
        el('button', { 'data-caf-enviar': '', disabled: '' }),
        el('button', { 'data-caf-vaciar': '', hidden: '' }),
    ]);
    const barra = el('button', { 'data-caf-barra': '', hidden: '' }, [el('span', {}, [el('span', { 'data-caf-cuenta': '' }), el('span', { 'data-caf-barra-total': '' })])]);
    const raiz = el('div', {
        'data-caf-pedido': '', 'data-numero': '525512345678', 'data-restaurante': restaurante, 'data-moneda': 'MXN', 'data-locale': 'es-MX',
        'data-mensaje': '', 'data-clave': CLAVE,
    }, [barra, el('p', { 'data-caf-toast': '', hidden: '' }), dialogo]);
    const entorno = montarPagina([sinJs, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, botones, sinJs, campo: (atributo) => raiz.querySelector(`[data-caf-${atributo}]`) };
}

const iniciar = () => iniciarPedido('caf', 'menuonl_cart_caf');
const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

test('la entrada de la plantilla P inicia el controlador compartido con su prefijo y su clave', () => {
    const entrada = readFileSync(new URL('../../resources/js/sitio-cafe-pedido.js', import.meta.url), 'utf8');

    assert.match(entrada, /iniciarPedido\('caf', 'menuonl_cart_caf'\)/);
    assert.doesNotMatch(entrada, /innerHTML|onclick|sessionStorage/);
});

test('con JavaScript aparecen los botones "+" y se oculta el enlace sin pedido armado', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Smash doble', precio: '165.00' }] });
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

    const nota = entorno.campo('lineas').querySelector('[data-caf-nota]');
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
    const entorno = pagina({ marcada: null, platillos: [{ producto: 1, nombre: 'Smash doble', precio: '165.00' }] });
    iniciar();
    entorno.botones[0].clic();
    entorno.campo('enviar').clic();

    assert.equal(entorno.abiertas.length, 0);

    entorno.raiz.querySelectorAll('[data-caf-modalidad]')[1].checked = true;
    entorno.campo('enviar').clic();
    assert.equal(entorno.abiertas.length, 1);
    assert.match(mensajeDe(entorno.abiertas[0][0]), /\*Entrega:\* Para recoger\n/);
});

test('un carrito guardado con un platillo que ya no está en la página se descarta', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Smash doble', precio: '165.00' }] }, {
        [CLAVE]: JSON.stringify([{ p: 99, v: null, c: 3, n: '' }, { p: 1, v: null, c: 2, n: 'sin ajonjolí' }]),
    });
    iniciar();

    assert.deepEqual(guardado(entorno), [{ p: 1, v: null, c: 2, n: 'sin ajonjolí' }]);
    assert.equal(entorno.campo('total').textContent, '$330.00');
    assert.equal(entorno.barra.hidden, false);
});

test('una cafetería arma líneas con variantes y notas ("leche de avena", "sin azúcar") y el enlace codifica & # % + ? y los saltos de línea', () => {
    const entorno = pagina({
        restaurante: 'Burger & Co #1 100% +queso?',
        platillos: [
            { producto: 1, nombre: 'Smash doble', precio: '165.00' },
            { producto: 2, variante: 21, nombre: 'Papas a mano & chipotle', nombreVariante: 'Grande', precio: '90.00' },
            { producto: 2, variante: 22, nombre: 'Papas a mano & chipotle', nombreVariante: 'Chica', precio: '65.00' },
        ],
    });
    iniciar();
    entorno.botones[0].clic();
    entorno.botones[1].clic();
    entorno.botones[2].clic();

    const lineas = entorno.campo('lineas').querySelectorAll('[data-caf-nota]');
    assert.equal(lineas.length, 3, 'Cada variante es su propia línea');
    lineas[0].value = 'sin cebolla, extra queso\nsin pepinillos';
    lineas[0].dispatchEvent({ type: 'input', target: lineas[0] });
    entorno.campo('cocina').value = 'Todo en bolsas separadas & salsa # aparte';
    entorno.campo('enviar').clic();

    assert.deepEqual(guardado(entorno).map((linea) => [linea.p, linea.v, linea.c]), [[1, null, 1], [2, 21, 1], [2, 22, 1]]);
    const [url] = entorno.abiertas[0];
    const texto = url.split('?text=')[1];
    assert.doesNotMatch(texto, /[&#?+ \n]/, 'Nada de caracteres de control en el texto codificado');
    const mensaje = mensajeDe(url);
    assert.match(mensaje, /Burger & Co #1 100% \+queso\?/);
    assert.match(mensaje, /Smash doble/);
    assert.match(mensaje, /Papas a mano & chipotle \(Grande\)/);
    assert.match(mensaje, /Papas a mano & chipotle \(Chica\)/);
    assert.match(mensaje, /sin cebolla, extra queso/);
    assert.match(mensaje, /salsa # aparte/);
});
