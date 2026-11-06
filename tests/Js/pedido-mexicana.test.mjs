import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Pedido de la plantilla N (Mexicana): el controlador compartido con el prefijo mex y la clave menuonl_cart_mex, con el mismo
// conjunto de data-mex-* que escribe resources/views/components/sitio/mexicana/pedido.blade.php y la vista del menú.
const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const CLAVE = 'menuonl_cart_mex_bahia';
const HOSTIL = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';

/**
 * @param {{modalidades?: string[], marcada?: string|null, restaurante?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ modalidades = ['domicilio', 'recoger'], marcada = 'domicilio', restaurante = 'La Bahía', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        class: 'mex-mas', hidden: '', 'data-mex-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '',
        'data-nombre': platillo.nombre, 'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio,
    }));
    const sinJs = el('a', { 'data-mex-sin-js': '', href: 'https://wa.me/525512345678' });
    const fila = el('template', { 'data-mex-plantilla-linea': '' }, [el('li', { class: 'mex-linea' }, [
        el('p', { 'data-mex-nombre': '' }),
        el('p', { 'data-mex-subtotal': '' }),
        el('input', { 'data-mex-nota': '' }),
        el('button', { 'data-mex-menos': '' }),
        el('span', { 'data-mex-cantidad': '' }),
        el('button', { 'data-mex-mas-uno': '' }),
    ])]);
    const dialogo = el('dialog', { 'data-mex-dialogo': '' }, [
        el('button', { 'data-mex-cerrar': '' }),
        el('p', { 'data-mex-vacio': '' }),
        el('ul', { 'data-mex-lineas': '' }),
        fila,
        el('fieldset', { 'data-mex-entrega': '' }, modalidades.map((modalidad) => el('input', {
            type: 'radio', name: 'mex-pedido-entrega', value: modalidad, 'data-mex-modalidad': '', ...(modalidad === marcada ? { checked: '' } : {}),
        }))),
        el('textarea', { 'data-mex-cocina': '' }),
        el('span', { 'data-mex-total': '' }),
        el('p', { 'data-mex-aviso-envio': '', hidden: '' }),
        el('div', { 'data-mex-enviado': '', hidden: '' }, [el('button', { 'data-mex-listo': '' }), el('button', { 'data-mex-aun-no': '' })]),
        el('div', { 'data-mex-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-mex-conservar': '' }), el('button', { 'data-mex-confirmar': '' })]),
        el('button', { 'data-mex-enviar': '', disabled: '' }),
        el('button', { 'data-mex-vaciar': '', hidden: '' }),
    ]);
    const barra = el('button', { 'data-mex-barra': '', hidden: '' }, [el('span', {}, [el('span', { 'data-mex-cuenta': '' }), el('span', { 'data-mex-barra-total': '' })])]);
    const raiz = el('div', {
        'data-mex-pedido': '', 'data-numero': '525512345678', 'data-restaurante': restaurante, 'data-moneda': 'MXN', 'data-locale': 'es-MX',
        'data-mensaje': '', 'data-clave': CLAVE,
    }, [barra, el('p', { 'data-mex-toast': '', hidden: '' }), dialogo]);
    const entorno = montarPagina([sinJs, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, botones, sinJs, campo: (atributo) => raiz.querySelector(`[data-mex-${atributo}]`) };
}

const iniciar = () => iniciarPedido('mex', 'menuonl_cart_mex');
const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

test('la entrada de la plantilla N inicia el controlador compartido con su prefijo y su clave', () => {
    const entrada = readFileSync(new URL('../../resources/js/sitio-mexicana-pedido.js', import.meta.url), 'utf8');

    assert.match(entrada, /iniciarPedido\('mex', 'menuonl_cart_mex'\)/);
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

    const nota = entorno.campo('lineas').querySelector('[data-mex-nota]');
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

    entorno.raiz.querySelectorAll('[data-mex-modalidad]')[1].checked = true;
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

test('un restaurante arma líneas con variantes y notas ("sin cebolla", "extra salsa") y el enlace codifica & # % + ? y los saltos de línea', () => {
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

    const lineas = entorno.campo('lineas').querySelectorAll('[data-mex-nota]');
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
