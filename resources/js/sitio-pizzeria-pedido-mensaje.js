// Pedido por WhatsApp de la plantilla H: reglas puras (limpieza, importes y mensaje). Sin DOM, para poder probarlas con Node.
//
// IMPORTANTE: el formato del mensaje debe mantenerse IGUAL al de menu.publico (resources/views/menu/publico.blade.php,
// función enviarPedidoWhatsApp). Si cambia allá, cambia aquí, y viceversa. Ver docs/plantilla-pizzeria.md. Debe ser idéntico al de sitio-cafeteria-pedido-mensaje.js (plantilla E) y al de sitio-bar-pedido-mensaje.js (plantilla F), al de sitio-pastel-pedido-mensaje.js (plantilla G); una prueba (tests/Js) lo comprueba.

export const LIMITES = {
    cantidad: 20,
    lineas: 30,
    notaLinea: 80,
    notaCocina: 200,
    mesa: 20,
    enlace: 2000,
};

const MARCAS_DE_FORMATO = /[*_~`]/g;
const CONTROL = /\p{Cc}|\p{Zl}|\p{Zp}/gu;

/**
 * Texto escrito por el cliente: sin caracteres de control ni marcas de formato de WhatsApp, espacios simples y largo limitado.
 */
export function limpiarTexto(texto, maximo) {
    const limpio = String(texto ?? '')
        .replace(CONTROL, ' ')
        .replace(MARCAS_DE_FORMATO, '')
        .replace(/\s+/g, ' ')
        .trim();

    return Array.from(limpio).slice(0, maximo).join('').trim();
}

/**
 * Mesa: letras, números, espacio, guion y guion bajo, hasta 20 (la misma idea que valida el servidor con ?mesa=).
 */
export function limpiarMesa(texto) {
    const limpio = String(texto ?? '').replace(/[^\p{L}\p{N} _-]/gu, '').replace(/\s+/g, ' ').trim();

    return Array.from(limpio).slice(0, LIMITES.mesa).join('').trim();
}

/**
 * Precio decimal del servidor ("78.00") a centavos enteros; NaN si no es un número válido y no negativo.
 */
export function centavos(precio) {
    const texto = String(precio ?? '').trim();

    if (!/^\d{1,9}(\.\d{1,2})?$/.test(texto)) {
        return Number.NaN;
    }

    return Math.round(Number(texto) * 100);
}

/**
 * Mismo criterio que el menú actual: Intl con el idioma y la moneda del restaurante, y el código de moneda cuando el símbolo es "$" y no es MXN.
 */
export function crearFormateador(moneda, locale) {
    let formateador = null;

    try {
        formateador = new Intl.NumberFormat(locale, { style: 'currency', currency: moneda });
    } catch {
        formateador = null;
    }

    return (valorEnCentavos) => {
        const valor = valorEnCentavos / 100;

        if (formateador === null) {
            return `${valor.toFixed(2)} ${moneda}`;
        }

        const ambiguo = moneda !== 'MXN' && formateador.formatToParts(valor).some((parte) => parte.type === 'currency' && parte.value === '$');

        return formateador.format(valor) + (ambiguo ? ` ${moneda}` : '');
    };
}

/**
 * Importes de las líneas y total. Cada línea: { cantidad, precio (centavos) }.
 */
export function totalizar(lineas) {
    return lineas.reduce((total, linea) => total + linea.cantidad * linea.precio, 0);
}

/**
 * Arma el mensaje y el enlace de wa.me.
 *
 * Si el enlace supera LIMITES.enlace se omiten platillos del final (se avisa con una línea en el mensaje) y, como último recurso,
 * la nota de cocina; el total siempre va completo. Devuelve { url, omitidas, sinNotaCocina }.
 *
 * @param {{numero: string, restaurante: string, mesa: string, notaCocina: string, lineas: Array<{cantidad: number, nombre: string, variante: string, nota: string, precio: number}>, formatear: (c: number) => string}} datos
 */
export function armarEnlace({ numero, restaurante, mesa, notaCocina, lineas, formatear }) {
    if (!/^[0-9]{8,20}$/.test(String(numero))) {
        return null;
    }

    const total = totalizar(lineas);
    const mesaTexto = limpiarMesa(mesa) || 'Para llevar';
    const cocina = limpiarTexto(notaCocina, LIMITES.notaCocina);

    const textoLinea = (linea) => {
        const variante = linea.variante ? ` (${linea.variante})` : '';
        const nota = limpiarTexto(linea.nota, LIMITES.notaLinea);

        return `• ${linea.cantidad}x ${linea.nombre}${variante} - ${formatear(linea.cantidad * linea.precio)}\n${nota ? `  _Nota: ${nota}_\n` : ''}`;
    };

    const construir = (incluidas, conCocina) => {
        const omitidas = lineas.length - incluidas;
        let items = lineas.slice(0, incluidas).map(textoLinea).join('');

        if (omitidas > 0) {
            items += `• … y ${omitidas} ${omitidas === 1 ? 'platillo más' : 'platillos más'} (te los confirmamos por aquí)\n`;
        }

        let mensaje = `Hola, quiero pedir en *${restaurante}*:\n\n`;
        mensaje += `*Mesa:* ${mesaTexto}\n\n`;
        mensaje += `*Pedido:*\n${items}\n`;

        if (conCocina && cocina) {
            mensaje += `*Nota para la cocina:* ${cocina}\n\n`;
        }

        mensaje += `*Total:* ${formatear(total)}`;

        return `https://wa.me/${numero}?text=${encodeURIComponent(mensaje)}`;
    };

    let incluidas = lineas.length;
    let conCocina = true;

    while (construir(incluidas, conCocina).length > LIMITES.enlace && incluidas > 0) {
        incluidas -= 1;
    }

    if (construir(incluidas, conCocina).length > LIMITES.enlace) {
        conCocina = false;
    }

    return { url: construir(incluidas, conCocina), omitidas: lineas.length - incluidas, sinNotaCocina: !conCocina && cocina !== '' };
}
