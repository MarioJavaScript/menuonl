// Migración del carrito que guardaba menu.publico antes del módulo compartido (clave menuonl_cart_{slug}, con nombre y precio por línea)
// al formato nuevo ({p, v, c, n}: solo ids, cantidad y nota). Nombre y precio NO se copian: salen siempre de los datos del servidor.
// Aquí solo se traduce la forma; el controlador valida cada línea contra los platillos de la página (existentes, no agotados, límites).

/**
 * @param {{getItem: (clave: string) => string|null, setItem: (clave: string, valor: string) => void, removeItem: (clave: string) => void}} almacen
 * @param {string} claveAntigua
 * @param {string} claveNueva
 * @returns {boolean} true si había un carrito antiguo (migrado o descartado).
 */
export function migrarCarritoAntiguo(almacen, claveAntigua, claveNueva) {
    try {
        const crudo = almacen.getItem(claveAntigua);

        if (crudo === null) {
            return false;
        }

        if (almacen.getItem(claveNueva) === null) {
            const lineas = traducir(crudo);

            if (lineas.length > 0) {
                almacen.setItem(claveNueva, JSON.stringify(lineas));
            }
        }

        almacen.removeItem(claveAntigua);

        return true;
    } catch {
        // Sin almacenamiento o con cuota llena: el carrito antiguo se queda y se reintenta en la próxima carga; nunca se rompe la página.
        return false;
    }
}

function traducir(crudo) {
    let datos;

    try {
        datos = JSON.parse(crudo);
    } catch {
        return [];
    }

    if (!Array.isArray(datos)) {
        return [];
    }

    return datos.flatMap((linea) => {
        const producto = Number.parseInt(linea?.producto_id, 10);
        const variante = linea?.variante_id ? Number.parseInt(linea.variante_id, 10) : null;
        const cantidad = Number.parseInt(linea?.cantidad, 10);

        if (!(producto > 0) || Number.isNaN(variante) || !(cantidad >= 1)) {
            return [];
        }

        return [{ p: producto, v: variante, c: cantidad, n: typeof linea?.notas === 'string' ? linea.notas : '' }];
    });
}
