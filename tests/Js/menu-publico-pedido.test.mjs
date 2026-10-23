import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { Nodo, montarPagina } from './ayudas/dom-falso.mjs';

// Referencia generada con el script en línea de menu.publico ANTES del PR 3 (ver el campo "origen" del JSON).
const referencia = JSON.parse(readFileSync(new URL('./fixtures/menu-publico-referencia.json', import.meta.url), 'utf8'));

const { iniciarPedido } = await import('../../resources/js/pedido/controlador.js');
const { migrarCarritoAntiguo } = await import('../../resources/js/pedido/migracion.js');
const { crearPresentacionHoja } = await import('../../resources/js/pedido/presentacion-hoja.js');
const { LIMITES } = await import('../../resources/js/pedido/mensaje.js');

const el = (etiqueta, atributos, hijos) => new Nodo(etiqueta, atributos, hijos);
const SLUG = 'cafe-mezquite';
const CLAVE = `menuonl_cart_m_${SLUG}`;
const CLAVE_ANTIGUA = `menuonl_cart_${SLUG}`;

/**
 * Página con el mismo conjunto de data-mo-* que escribe menu.publico.blade.php y dish-card.blade.php.
 *
 * @param {{moneda?: string, locale?: string, numero?: string, restaurante?: string, mesa?: string, platillos: Array<{producto: number, variante?: number, nombre: string, nombreVariante?: string, precio: string}>}} datos
 */
function pagina({ moneda = 'MXN', locale = 'es-MX', numero = '525512345678', restaurante = 'Cafe Mezquite', mesa = '', platillos }, almacenamiento = {}) {
    const botones = platillos.map((platillo) => el('button', {
        'data-mo-agregar': '', 'data-producto': platillo.producto, 'data-variante': platillo.variante ?? '', 'data-nombre': platillo.nombre,
        'data-nombre-variante': platillo.nombreVariante ?? '', 'data-precio': platillo.precio, class: 'btn-cart-add',
    }));
    const fila = el('template', { 'data-mo-plantilla-linea': '' }, [el('div', { class: 'py-3' }, [
        el('h4', {}, [el('span', { 'data-mo-nombre': '' }), el('span', { 'data-mo-variante': '' })]),
        el('span', { 'data-mo-subtotal': '' }),
        el('button', { 'data-mo-menos': '' }),
        el('span', {}, [el('span', { 'data-mo-cantidad': '' })]),
        el('button', { 'data-mo-mas-uno': '' }),
        el('input', { 'data-mo-nota': '' }),
    ])]);
    const dialogo = el('div', { id: 'cartModalBackdrop', 'data-mo-dialogo': '', class: 'cart-modal-backdrop' }, [el('div', { class: 'cart-drawer' }, [
        el('button', { id: 'btnCloseCartModal', 'data-mo-cerrar': '' }),
        el('button', { id: 'btnVaciarCarrito', 'data-mo-vaciar': '', hidden: '' }),
        el('div', { 'data-mo-confirmar-vaciar': '', hidden: '' }, [el('button', { 'data-mo-conservar': '' }), el('button', { 'data-mo-confirmar': '' })]),
        el('div', { 'data-mo-enviado': '', hidden: '' }, [el('button', { 'data-mo-listo': '' }), el('button', { 'data-mo-aun-no': '' })]),
        el('p', { 'data-mo-aviso-envio': '', hidden: '' }),
        el('div', { id: 'cartItemsContainer' }, [el('p', { 'data-mo-vacio': '' }), el('div', { 'data-mo-lineas': '' }), fila]),
        el('input', { id: 'inputMesa', 'data-mo-mesa': '', value: mesa }),
        el('textarea', { id: 'inputNotasGenerales', 'data-mo-cocina': '' }),
        el('span', { id: 'modalCartTotal', 'data-mo-total': '' }),
        el('button', { id: 'btnEnviarWhatsApp', 'data-mo-enviar': '' }),
    ])]);
    const barra = el('button', { id: 'floatingCartBar', 'data-mo-barra': '', hidden: '' }, [el('span', { 'data-mo-cuenta': '' }), el('span', { 'data-mo-barra-total': '' })]);
    const raiz = el('div', {
        'data-mo-pedido': '', 'data-numero': numero, 'data-restaurante': restaurante, 'data-moneda': moneda, 'data-locale': locale,
        'data-mesa': mesa, 'data-clave': CLAVE, 'data-clave-antigua': CLAVE_ANTIGUA,
    }, [barra, dialogo]);
    const toast = el('div', { id: 'cartToast' }, [el('span', { id: 'cartToastMessage' })]);
    const entorno = montarPagina([toast, ...botones, raiz], { almacenamiento });

    return { ...entorno, raiz, barra, dialogo, botones, toast, campo: (atributo) => raiz.querySelector(`[data-mo-${atributo}]`) };
}

function iniciar(entorno) {
    const { raiz } = entorno;
    migrarCarritoAntiguo(globalThis.localStorage, raiz.dataset.claveAntigua, raiz.dataset.clave);
    iniciarPedido('mo', 'menuonl_cart_m', { presentacion: crearPresentacionHoja, textoAgregado: (etiqueta) => `${etiqueta} agregado` });
}

const mensajeDe = (url) => decodeURIComponent(url.split('text=')[1]);
const guardado = (entorno) => JSON.parse(entorno.memoria.get(CLAVE) ?? 'null');

function platillosDe(carrito) {
    const vistos = new Map();

    carrito.forEach((linea) => vistos.set(`${linea.producto_id}:${linea.variante_id ?? ''}`, {
        producto: linea.producto_id, variante: linea.variante_id ?? undefined, nombre: linea.nombre, nombreVariante: linea.variante_nombre ?? '', precio: Number(linea.precio).toFixed(2),
    }));

    return [...vistos.values()];
}

function enviarCaso(caso, { precioActual } = {}) {
    const platillos = platillosDe(caso.carritoAntiguo).map((p) => (precioActual ? { ...p, precio: precioActual } : p));
    const entorno = pagina({ moneda: caso.moneda, locale: caso.locale, numero: caso.numero, restaurante: caso.restaurante, platillos }, { [CLAVE_ANTIGUA]: JSON.stringify(caso.carritoAntiguo) });
    iniciar(entorno);
    entorno.campo('mesa').value = caso.mesa;
    entorno.campo('cocina').value = caso.notaCocina;
    entorno.campo('enviar').clic();

    return entorno;
}

for (const caso of referencia.casos.filter((c) => !c.cambiaAPropósito)) {
    test(`un pedido normal llega igual que antes (carrito en formato antiguo -> mensaje): ${caso.id}`, () => {
        const entorno = enviarCaso(caso);

        assert.equal(entorno.abiertas.length, 1);
        assert.equal(entorno.abiertas[0][0], caso.urlAntes);
        assert.equal(entorno.campo('total').textContent, caso.totalAntes);
    });
}

test('entradas raras: solo cambian la mesa, las marcas y saltos de las notas y la cantidad máxima', () => {
    const por = (id) => referencia.casos.find((c) => c.id === id);
    const mensaje = (id) => mensajeDe(enviarCaso(por(id)).abiertas[0][0]);
    const antes = (id) => mensajeDe(por(id).urlAntes);

    assert.equal(mensaje('rara-mesa-simbolos'), antes('rara-mesa-simbolos').replace('*Mesa:* Barra/2', '*Mesa:* Barra 2'));
    assert.equal(mensaje('rara-mesa-numeral'), antes('rara-mesa-numeral').replace('*Mesa:* Mesa #3', '*Mesa:* Mesa 3'));
    assert.equal(
        mensaje('rara-notas-con-marcas'),
        antes('rara-notas-con-marcas').replace('_Nota: con _leche_ *extra*_', '_Nota: con leche extra_').replace('sin *hielo*\ny ~nada~', 'sin hielo y nada'),
    );
    assert.equal(
        mensaje('rara-cantidad-fuera-de-limite'),
        antes('rara-cantidad-fuera-de-limite').replace('500x Latte - $27,500.00', '20x Latte - $1,100.00').replace('*Total:* $27,500.00', '*Total:* $1,100.00'),
    );
});

test('carrito antiguo: se migra con ids validados, nombre y precio vigentes del servidor, y se borra la clave antigua', () => {
    const antiguo = [
        { producto_id: 1, variante_id: null, nombre: 'Nombre viejo', variante_nombre: null, precio: 1, cantidad: 2, notas: 'sin *azucar*' },
        { producto_id: 2, variante_id: 20, nombre: 'Chai', variante_nombre: 'Grande', precio: 1, cantidad: 1, notas: '' },
        { producto_id: 99, variante_id: null, nombre: 'Ya no existe', variante_nombre: null, precio: 5, cantidad: 3, notas: '' },
    ];
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }, { producto: 2, variante: 20, nombre: 'Chai', nombreVariante: 'Grande', precio: '60.00' }] }, { [CLAVE_ANTIGUA]: JSON.stringify(antiguo) });
    iniciar(entorno);

    assert.equal(entorno.memoria.has(CLAVE_ANTIGUA), false);
    assert.deepEqual(guardado(entorno), [{ p: 1, v: null, c: 2, n: 'sin azucar' }, { p: 2, v: 20, c: 1, n: '' }]);
    assert.doesNotMatch(entorno.memoria.get(CLAVE), /Nombre viejo|precio|nombre/);
    assert.equal(entorno.campo('total').textContent, '$170.00');
    assert.equal(entorno.campo('cuenta').textContent, '3');
    assert.equal(entorno.barra.hidden, false);
});

test('carrito antiguo: platillos agotados u ocultos (sin botón en la página) se descartan', () => {
    const antiguo = [{ producto_id: 1, variante_id: null, nombre: 'Latte', variante_nombre: null, precio: 55, cantidad: 1, notas: '' }];
    const entorno = pagina({ platillos: [{ producto: 2, nombre: 'Otro', precio: '10.00' }] }, { [CLAVE_ANTIGUA]: JSON.stringify(antiguo) });
    iniciar(entorno);

    assert.equal(entorno.memoria.has(CLAVE_ANTIGUA), false);
    assert.deepEqual(guardado(entorno), []);
    assert.equal(entorno.barra.hidden, true);
    assert.equal(entorno.campo('enviar').disabled, true);
});

test('carrito antiguo corrupto o con otra forma: se descarta sin romper y sin dejar nada a medias', () => {
    for (const crudo of ['{no es json', '"texto"', '{"a":1}', 'null', '[1,"x",null,{"producto_id":"abc","cantidad":2},{"producto_id":1,"cantidad":0}]']) {
        const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] }, { [CLAVE_ANTIGUA]: crudo });
        iniciar(entorno);

        assert.equal(entorno.memoria.has(CLAVE_ANTIGUA), false, crudo);
        assert.deepEqual(guardado(entorno), [], crudo);
    }
});

test('si ya existe el carrito nuevo, manda el nuevo y la clave antigua se borra igual', () => {
    const entorno = pagina(
        { platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] },
        { [CLAVE]: JSON.stringify([{ p: 1, v: null, c: 4, n: '' }]), [CLAVE_ANTIGUA]: JSON.stringify([{ producto_id: 1, variante_id: null, cantidad: 9, notas: '' }]) },
    );
    iniciar(entorno);

    assert.equal(entorno.memoria.has(CLAVE_ANTIGUA), false);
    assert.equal(guardado(entorno)[0].c, 4);
});

test('la migración no se rompe si el almacenamiento falla', () => {
    const almacen = { getItem: () => '[]', setItem: () => { throw new Error('cuota'); }, removeItem: () => { throw new Error('bloqueado'); } };

    assert.doesNotThrow(() => migrarCarritoAntiguo(almacen, CLAVE_ANTIGUA, CLAVE));
    assert.doesNotThrow(() => migrarCarritoAntiguo({ getItem: () => { throw new Error('privado'); } }, CLAVE_ANTIGUA, CLAVE));
});

test('límites: cantidad por platillo y número de líneas, tanto al cargar como al agregar', () => {
    const muchos = Array.from({ length: LIMITES.lineas + 5 }, (_, i) => ({ producto_id: i + 1, variante_id: null, cantidad: 500, notas: 'x'.repeat(300) }));
    const platillos = muchos.map((linea) => ({ producto: linea.producto_id, nombre: `P${linea.producto_id}`, precio: '10.00' }));
    const entorno = pagina({ platillos }, { [CLAVE_ANTIGUA]: JSON.stringify(muchos) });
    iniciar(entorno);

    assert.equal(guardado(entorno).length, LIMITES.lineas);
    assert.ok(guardado(entorno).every((linea) => linea.c === LIMITES.cantidad && linea.n.length <= LIMITES.notaLinea));

    entorno.botones[LIMITES.lineas].clic();
    assert.equal(guardado(entorno).length, LIMITES.lineas, 'no entra la línea 31');
    assert.match(entorno.toast.querySelector('#cartToastMessage').textContent, /muchos platillos/);

    entorno.botones[0].clic();
    assert.equal(guardado(entorno)[0].c, LIMITES.cantidad, 'no pasa de 20');
    assert.equal(entorno.toast.querySelector('#cartToastMessage').textContent, `Máximo ${LIMITES.cantidad} por platillo.`);
});

test('agregar: aviso con el texto de siempre, línea con variante en su propia ranura y total en centavos', () => {
    const entorno = pagina({ platillos: [{ producto: 1, variante: 5, nombre: 'Latte', nombreVariante: 'Grande', precio: '19.99' }, { producto: 2, nombre: 'Pan', precio: '10.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();
    entorno.botones[0].clic();
    entorno.botones[0].clic();

    assert.equal(entorno.toast.querySelector('#cartToastMessage').textContent, 'Latte (Grande) agregado');
    assert.equal(entorno.toast.classList.contains('visible'), true);
    assert.equal(entorno.campo('barra-total').textContent, '$59.97');

    const fila = entorno.campo('lineas').children[0];
    assert.equal(fila.querySelector('[data-mo-nombre]').textContent, 'Latte');
    assert.equal(fila.querySelector('[data-mo-variante]').textContent, '(Grande)');
    assert.equal(fila.querySelector('[data-mo-menos]').getAttribute('aria-label'), 'Quitar uno de Latte (Grande)');

    entorno.botones[1].clic();
    assert.equal(entorno.campo('lineas').children[1].querySelector('[data-mo-variante]').hidden, true);
});

test('la nota de cada línea se limpia y se guarda al escribir; las marcas de WhatsApp no llegan al mensaje', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();
    const nota = entorno.campo('lineas').querySelector('[data-mo-nota]');
    nota.value = '  sin *hielo*\n  y _poco_  ';
    nota.dispatchEvent({ type: 'input', target: nota });

    assert.equal(guardado(entorno)[0].n, 'sin hielo y poco');
});

test('mesa de ?mesa= se limpia con la regla del módulo; vacía sale "Para llevar"', () => {
    const entorno = pagina({ mesa: 'Barra/2', platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);

    assert.equal(entorno.campo('mesa').value, 'Barra 2');
    assert.equal(entorno.campo('mesa').maxLength, LIMITES.mesa);

    entorno.botones[0].clic();
    entorno.campo('mesa').value = '   ';
    entorno.campo('enviar').clic();
    assert.match(mensajeDe(entorno.abiertas[0][0]), /\*Mesa:\* Para llevar\n/);
});

test('número inválido: no se abre WhatsApp, se avisa y el cajón sigue usable', () => {
    const entorno = pagina({ numero: '123', platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();
    entorno.campo('enviar').clic();

    assert.equal(entorno.abiertas.length, 0);
    assert.match(entorno.toast.querySelector('#cartToastMessage').textContent, /No pudimos armar el pedido/);
});

test('sin contenedor (pedidos desactivados, cerrado o sin número válido) el módulo no hace nada', () => {
    const boton = el('button', { 'data-mo-agregar': '', 'data-producto': 1, 'data-nombre': 'Latte', 'data-precio': '55.00' });
    const entorno = montarPagina([boton]);

    assert.doesNotThrow(() => iniciarPedido('mo', 'menuonl_cart_m', { presentacion: crearPresentacionHoja }));
    boton.clic();
    assert.equal(entorno.memoria.size, 0);
});

test('cajón: abre con "open", bloquea el scroll, enfoca cerrar y no agrega mientras está abierto', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();
    entorno.barra.clic();

    assert.equal(entorno.dialogo.classList.contains('open'), true);
    assert.equal(entorno.barra.getAttribute('aria-expanded'), 'true');
    assert.equal(entorno.documento.body.style.overflow, 'hidden');
    assert.equal(entorno.documento.activeElement.attrs.get('id'), 'btnCloseCartModal');

    entorno.botones[0].clic();
    assert.equal(guardado(entorno)[0].c, 1, 'con el cajón abierto no se agrega');
});

test('cajón: Escape, clic en el fondo, el botón cerrar y el arrastre (hoja-cerrar) lo cierran y devuelven el foco a la barra', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();

    const cierres = [
        () => entorno.documento.listeners.get('keydown').forEach((f) => f({ key: 'Escape' })),
        () => entorno.dialogo.dispatchEvent({ type: 'click', target: entorno.dialogo }),
        () => entorno.campo('cerrar').clic(),
        () => entorno.dialogo.children[0].dispatchEvent({ type: 'hoja-cerrar' }),
    ];

    for (const cerrar of cierres) {
        entorno.barra.clic();
        assert.equal(entorno.dialogo.classList.contains('open'), true);
        cerrar();
        assert.equal(entorno.dialogo.classList.contains('open'), false);
        assert.equal(entorno.dialogo.classList.contains('cerrando'), false, 'con movimiento reducido no hay animación pendiente');
        assert.equal(entorno.documento.body.style.overflow, '');
        assert.equal(entorno.barra.getAttribute('aria-expanded'), 'false');
        assert.equal(entorno.documento.activeElement, entorno.barra);
    }
});

test('cajón: la trampa de Tab da la vuelta entre el primer y el último control', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();
    entorno.barra.clic();

    const controles = entorno.dialogo.querySelectorAll('button').filter((boton) => !boton.disabled && boton.offsetParent !== null);
    const primero = controles[0];
    const ultimo = controles[controles.length - 1];
    const tab = (shiftKey) => {
        const evento = { key: 'Tab', shiftKey, preventDefault() { this.evitado = true; } };
        entorno.documento.listeners.get('keydown').forEach((f) => f(evento));

        return evento;
    };

    primero.focus();
    assert.equal(tab(true).evitado, true);
    assert.equal(entorno.documento.activeElement, ultimo);
    assert.equal(tab(false).evitado, true);
    assert.equal(entorno.documento.activeElement, primero);
});

test('vaciar: pide confirmación, conserva o vacía, y "Ya lo mandé" limpia el pedido y la nota de cocina', () => {
    const entorno = pagina({ platillos: [{ producto: 1, nombre: 'Latte', precio: '55.00' }] });
    iniciar(entorno);
    entorno.botones[0].clic();
    entorno.barra.clic();

    entorno.campo('vaciar').clic();
    assert.equal(entorno.campo('confirmar-vaciar').hidden, false);
    entorno.campo('conservar').clic();
    assert.equal(entorno.campo('confirmar-vaciar').hidden, true);
    assert.equal(guardado(entorno).length, 1);

    entorno.campo('vaciar').clic();
    entorno.campo('confirmar').clic();
    assert.deepEqual(guardado(entorno), []);
    assert.equal(entorno.toast.querySelector('#cartToastMessage').textContent, 'Vaciaste tu pedido.');

    entorno.campo('cerrar').clic();
    entorno.botones[0].clic();
    entorno.barra.clic();
    entorno.campo('cocina').value = 'sin cubiertos';
    entorno.campo('enviar').clic();
    assert.equal(entorno.campo('enviado').hidden, false);
    entorno.campo('listo').clic();
    assert.deepEqual(guardado(entorno), []);
    assert.equal(entorno.campo('cocina').value, '');
    assert.equal(entorno.dialogo.classList.contains('open'), false);
});
