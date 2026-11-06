import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Pedido de la plantilla M (Hamburguesas): el controlador compartido con el prefijo ham y la clave menuonl_cart_ham, con el mismo
// conjunto de data-ham-* que escribe resources/views/components/sitio/hamburguesas/pedido.blade.php y la vista del menú.
const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const CLAVE = 'menuonl_cart_ham_bahia';
const HOSTIL = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';

/**
 * @param {{modalidades?: string[], marcada?: string|null, restaurante?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ modalidades = ['domicilio', 'recoger'], marcada = 'domicilio', restaurante = 'La Bahía', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        class: 'ham-mas', hidden: '', 'data-ham-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '',
        'data-nombre': platillo.nombre, 'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio,
    }));
    const sinJs = el('a', { 'data-ham-sin-js': '', href: 'https://wa.me/525512345678' });
    const fila = el('template', { 'data-ham-plantilla-linea': '' }, [el('li', { class: 'ham-linea' }, [
        el('p', { 'data-ham-nombre': '' }),
        el('p', { 'data-ham-subtotal': '' }),
        el('input', { 'data-ham-nota': '' }),
        el('button', { 'data-ham-menos': '' }),
        el('span', { 'data-ham-cantidad': '' }),
        el('button', { 'data-ham-mas-uno': '' }),
    ])]);
    const dialogo = el('dialog', { 'data-ham-dialogo': '' }, [
        el('button', { 'data-ham-cerrar': '' }),
        el('p', { 'data-ham-vacio': '' }),
        el('ul', { 'data-ham-lineas': '' }),
        fila,
        el('fieldset', { 'data-ham-entrega': '' }, modalidades.map((modalidad) => el('input', {
            type: 'radio', name: 'ham-pedido-entrega', value: modalidad, 'data-ham-modalidad': '', ...(modalidad === marcada ? { checked: '' } : {}),
        }))),
        el('textarea', { 'data-ham-cocina': '' }),
        el('span', { 'data-ham-total': '' }),
        el('p', { 'data-ham-aviso-envio': '', hidden: '' }),
        el('div', { 'data-ham-enviado': '', hidden: '' }, [el('button', { 'data-ham-listo': '' }), el('button', { 'data-ham-aun-no': '' })]),
        el('div', { 'data-ham-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-ham-conservar': '' }), el('button', { 'data-ham-confirmar': '' })]),
        el('button', { 'data-ham-enviar': '', disabled: '' }),
        el('button', { 'data-ham-vaciar': '', hidden: '' }),
    ]);
    const barra = el('button', { 'data-ham-barra': '', hidden: '' }, [el('span', {}, [el('span', { 'data-ham-cuenta': '' }), el('span', { 'data-ham-barra-total': '' })])]);
    const raiz = el('div', {
        'data-ham-pedido': '', 'data-numero': '525512345678', 'data-restaurante': restaurante, 'data-moneda': 'MXN', 'data-locale': 'es-MX',
        'data-mensaje': '', 'data-clave': CLAVE,
    }, [barra, el('p', { 'data-ham-toast': '', hidden: '' }), dialogo]);
    const entorno = montarPagina([sinJs, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, botones, sinJs, campo: (atributo) => raiz.querySelector(`[data-ham-${atributo}]`) };
}

const iniciar = () => iniciarPedido('ham', 'menuonl_cart_ham');
const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

test('la entrada de la plantilla M inicia el controlador compartido con su prefijo y su clave', () => {
    const entrada = readFileSync(new URL('../../resources/js/sitio-hamburguesas-pedido.js', import.meta.url), 'utf8');

    assert.match(entrada, /iniciarPedido\('ham', 'menuonl_cart_ham'\)/);
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

    const nota = entorno.campo('lineas').querySelector('[data-ham-nota]');
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

    entorno.raiz.querySelectorAll('[data-ham-modalidad]')[1].checked = true;
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

test('una hamburguesería arma líneas con variantes y notas ("sin cebolla", "extra queso") y el enlace codifica & # % + ? y los saltos de línea', () => {
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

    const lineas = entorno.campo('lineas').querySelectorAll('[data-ham-nota]');
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
