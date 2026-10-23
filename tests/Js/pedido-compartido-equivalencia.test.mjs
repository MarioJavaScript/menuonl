import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

// Datos de referencia generados con el código de E, F, G y H ANTES de moverlo al módulo compartido (los cuatro coincidían).
// No se regeneran: si una prueba falla, el cambio alteró lo que recibe el restaurante.
const referencia = JSON.parse(readFileSync(new URL('./fixtures/pedido-mensaje-referencia.json', import.meta.url), 'utf8'));

const modulos = {
    compartido: await import('../../resources/js/pedido/mensaje.js'),
    cafeteria: await import('../../resources/js/sitio-cafeteria-pedido-mensaje.js'),
    bar: await import('../../resources/js/sitio-bar-pedido-mensaje.js'),
    pastel: await import('../../resources/js/sitio-pastel-pedido-mensaje.js'),
    pizzeria: await import('../../resources/js/sitio-pizzeria-pedido-mensaje.js'),
};

const real = (valor) => (valor === '__undefined__' ? undefined : valor);

for (const [nombre, modulo] of Object.entries(modulos)) {
    test(`${nombre}: los límites son los de referencia`, () => {
        assert.deepEqual(modulo.LIMITES, referencia.limites);
    });

    test(`${nombre}: el mensaje y el enlace son idénticos a la referencia`, () => {
        for (const caso of referencia.casos) {
            const formatear = modulo.crearFormateador(caso.moneda, caso.locale);
            const total = modulo.totalizar(caso.lineas);
            const resultado = modulo.armarEnlace({
                numero: caso.numero,
                restaurante: caso.restaurante,
                mesa: caso.mesa,
                notaCocina: caso.notaCocina,
                lineas: caso.lineas,
                formatear,
            });

            assert.deepEqual({ resultado, total, totalFormateado: formatear(total) }, caso.esperado, caso.nombre);

            if (resultado !== null) {
                assert.ok(resultado.url.length <= modulo.LIMITES.enlace || resultado.sinNotaCocina || resultado.omitidas === caso.lineas.length, caso.nombre);
            }
        }
    });

    test(`${nombre}: la limpieza de texto, mesa y precios es idéntica a la referencia`, () => {
        for (const x of referencia.limpiarTexto) {
            assert.equal(modulo.limpiarTexto(real(x.entrada), x.maximo), x.esperado, JSON.stringify(x));
        }

        for (const x of referencia.limpiarMesa) {
            assert.equal(modulo.limpiarMesa(real(x.entrada)), x.esperado, JSON.stringify(x));
        }

        for (const x of referencia.centavos) {
            const obtenido = modulo.centavos(real(x.entrada));

            assert.equal(Number.isNaN(obtenido) ? 'NaN' : obtenido, x.esperado, JSON.stringify(x));
        }
    });

    test(`${nombre}: el formato de moneda es idéntico a la referencia`, () => {
        for (const x of referencia.importes) {
            assert.equal(modulo.crearFormateador(x.moneda, x.locale)(x.centavos), x.esperado, JSON.stringify(x));
        }
    });
}

test('la referencia cubre casos normales, hostiles y largos', () => {
    for (const prefijo of ['normal', 'hostil', 'largo']) {
        assert.ok(referencia.casos.some((caso) => caso.nombre.startsWith(prefijo)), prefijo);
    }

    assert.ok(referencia.casos.some((caso) => caso.esperado.resultado?.omitidas > 0));
    assert.ok(referencia.casos.some((caso) => caso.esperado.resultado?.sinNotaCocina === true));
    assert.ok(referencia.casos.some((caso) => caso.esperado.resultado === null));
});
