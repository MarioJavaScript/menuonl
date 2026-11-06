import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ENTREGAS, LIMITES, armarEnlace, centavos, crearFormateador, limpiarTexto, totalizar } from '../../resources/js/sitio-cortes-pedido-mensaje.js';

const formatear = crearFormateador('MXN', 'es-MX');
const texto = (enlace) => decodeURIComponent(enlace.url.split('?text=')[1]);
const base = { numero: '525512345678', restaurante: 'Cafe Mezquite', entrega: 'recoger', notaCocina: '', formatear };

test('centavos solo acepta decimales del servidor', () => {
    assert.equal(centavos('78.00'), 7800);
    assert.equal(centavos('9.5'), 950);
    for (const malo of ['', '-1', '1e3', '12,5', 'abc', '1.234', null, undefined, '999999999999']) {
        assert.ok(Number.isNaN(centavos(malo)), String(malo));
    }
});

test('limpiarTexto quita control, marcas de formato y recorta', () => {
    assert.equal(limpiarTexto('  sin *azucar*\n_ni_ \u0000hielo~`  ', 80), 'sin azucar ni hielo');
    assert.equal(limpiarTexto('x'.repeat(500), LIMITES.notaLinea).length, LIMITES.notaLinea);
    assert.equal(limpiarTexto(null, 10), '');
});

test('la entrega solo puede ser Domicilio o Para recoger: no hay pedidos de mesa', () => {
    assert.deepEqual({ ...ENTREGAS }, { domicilio: 'Domicilio', recoger: 'Para recoger' });
    assert.ok(Object.isFrozen(ENTREGAS));

    const lineas = [{ cantidad: 1, nombre: 'Latte', variante: '', nota: '', precio: 5500 }];

    for (const entrega of ['', '   ', '3', 'mesa', 'Mesa 3', 'Domicilio', 'toString', '__proto__', 'constructor', undefined, null]) {
        assert.equal(armarEnlace({ ...base, entrega, lineas }), null, String(entrega));
    }
});

test('el mensaje conserva el formato de menu.publico', () => {
    const resultado = armarEnlace({
        ...base,
        notaCocina: 'Sin cubiertos',
        lineas: [
            { cantidad: 2, nombre: 'Latte', variante: 'Grande', nota: 'sin azucar', precio: 5500 },
            { cantidad: 1, nombre: 'Chai', variante: '', nota: '', precio: 5000 },
        ],
    });

    assert.equal(texto(resultado), [
        'Hola, quiero pedir en *Cafe Mezquite*:',
        '',
        '*Entrega:* Para recoger',
        '',
        '*Pedido:*',
        '• 2x Latte (Grande) - $110.00',
        '  _Nota: sin azucar_',
        '• 1x Chai - $50.00',
        '',
        '*Nota para la cocina:* Sin cubiertos',
        '',
        '*Total:* $160.00',
    ].join('\n'));
    assert.ok(resultado.url.startsWith('https://wa.me/525512345678?text='));
    assert.equal(resultado.omitidas, 0);
});

test('a domicilio dice Domicilio y sin nota de cocina no deja hueco', () => {
    const resultado = armarEnlace({ ...base, entrega: 'domicilio', lineas: [{ cantidad: 1, nombre: 'Latte', variante: '', nota: '', precio: 5500 }] });

    assert.equal(texto(resultado), 'Hola, quiero pedir en *Cafe Mezquite*:\n\n*Entrega:* Domicilio\n\n*Pedido:*\n• 1x Latte - $55.00\n\n*Total:* $55.00');
    assert.doesNotMatch(texto(resultado), /Mesa/);
});

test('el mensaje del restaurante va al final, limpio y con su largo máximo', () => {
    const lineas = [{ cantidad: 1, nombre: 'Latte', variante: '', nota: '', precio: 5500 }];
    const resultado = armarEnlace({ ...base, mensaje: '  Te confirmamos *pronto*\n_gracias_  ', lineas });

    assert.ok(texto(resultado).endsWith('*Total:* $55.00\n\nTe confirmamos pronto gracias'));
    assert.ok(texto(armarEnlace({ ...base, mensaje: 'x'.repeat(500), lineas })).endsWith(`\n\n${'x'.repeat(LIMITES.mensaje)}`));
    assert.ok(texto(armarEnlace({ ...base, mensaje: '   ', lineas })).endsWith('*Total:* $55.00'));
});

test('nombres y notas hostiles solo viajan codificados', () => {
    const hostil = '</script><img src=x onerror=alert(1)>&?#=% ${x} `y`';
    const resultado = armarEnlace({ ...base, lineas: [{ cantidad: 1, nombre: hostil, variante: '', nota: hostil, precio: 100 }] });
    const parametro = resultado.url.split('?text=')[1];

    assert.doesNotMatch(parametro, /[<>&?# ]|%(?![0-9A-F]{2})/);
    assert.equal(resultado.url.match(/\?/g).length, 1);
    assert.ok(texto(resultado).includes(hostil.replace(/`/g, '')));
});

test('un numero invalido no genera enlace', () => {
    for (const numero of ['', '123', '12345abc', '1'.repeat(21), 'javascript:alert(1)']) {
        assert.equal(armarEnlace({ ...base, numero, lineas: [] }), null, numero);
    }
});

test('el enlace se recorta, avisa cuantos platillos omite y conserva el total completo', () => {
    const lineas = Array.from({ length: 30 }, (_, i) => ({ cantidad: 3, nombre: `Platillo numero ${i} con nombre bastante largo ñandú`, variante: 'Grande', nota: 'nota larga para ocupar espacio ' + i, precio: 12345 }));
    const resultado = armarEnlace({ ...base, notaCocina: 'a'.repeat(300), lineas });

    assert.ok(resultado.url.length <= LIMITES.enlace);
    assert.ok(resultado.omitidas > 0);
    assert.match(texto(resultado), new RegExp(`y ${resultado.omitidas} platillos más`));
    assert.ok(texto(resultado).endsWith(`*Total:* ${formatear(totalizar(lineas))}`));
});

test('formato de moneda agrega el codigo cuando el simbolo es ambiguo', () => {
    assert.match(crearFormateador('USD', 'es-US')(1050), /USD$/);
    assert.doesNotMatch(crearFormateador('MXN', 'es-MX')(1050), /MXN/);
    assert.equal(crearFormateador('XXX_MALA', 'es')(1050), '10.50 XXX_MALA');
});

test('el modulo de F y el de E producen el mismo mensaje (el formato debe seguir igual al de menu.publico)', async () => {
    const cafeteria = await import('../../resources/js/sitio-cafeteria-pedido-mensaje.js');
    const entrada = {
        ...base,
        notaCocina: 'Sin cubiertos',
        lineas: [
            { cantidad: 2, nombre: 'Negroni', variante: 'Doble', nota: 'sin *hielo*', precio: 29000 },
            { cantidad: 1, nombre: 'Mar de Cortés', variante: '', nota: '', precio: 17000 },
        ],
    };

    assert.equal(armarEnlace(entrada).url, cafeteria.armarEnlace({ ...entrada, formatear: cafeteria.crearFormateador('MXN', 'es-MX') }).url);
    assert.deepEqual(LIMITES, cafeteria.LIMITES);
});
