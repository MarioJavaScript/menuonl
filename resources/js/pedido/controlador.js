// Pedido por WhatsApp del menú de las plantillas E, F, G y H. Sin dependencias.
//
// - Nombres, precios, número, moneda y mesa salen de atributos de datos que escribe el servidor; nada de lo que escribe el cliente
//   define un precio o un nombre.
// - localStorage guarda solo identificadores, cantidades y notas. Cada carga se vuelve a contrastar con los platillos de la página:
//   lo que ya no existe o está agotado se descarta.
// - Todo el contenido se inserta con textContent y nodos del DOM; no hay HTML armado con texto ni manejadores en línea.
// - El formato del mensaje vive en pedido/mensaje.js y debe mantenerse igual al de menu.publico.
// - Cada plantilla lo inicia desde su propio archivo con el prefijo de sus atributos de datos (data-{prefijo}-*) y la clave
//   de localStorage que usa por defecto.
import { LIMITES, armarEnlace, centavos, crearFormateador, limpiarMesa, limpiarTexto, totalizar } from './mensaje.js';

/**
 * @param {string} prefijo Prefijo de los atributos de datos de la plantilla (cal, bar, pas, piz).
 * @param {string} claveDefecto Clave de localStorage si el servidor no manda data-clave.
 */
export function iniciarPedido(prefijo, claveDefecto) {
    const atributo = (nombre) => `data-${prefijo}-${nombre}`;
    const selector = (nombre) => `[${atributo(nombre)}]`;
    const raiz = document.querySelector(selector('pedido'));

    if (!raiz) {
        return;
    }

    const consultar = (css) => raiz.querySelector(css);
    const dialogo = consultar(selector('dialogo'));
    const barra = consultar(selector('barra'));
    const lista = consultar(selector('lineas'));
    const plantilla = consultar(`template${selector('plantilla-linea')}`);
    const vacio = consultar(selector('vacio'));
    const campoMesa = consultar(selector('mesa'));
    const campoCocina = consultar(selector('cocina'));
    const botonEnviar = consultar(selector('enviar'));
    const botonVaciar = consultar(selector('vaciar'));
    const confirmarVaciar = consultar(selector('confirmar-vaciar'));
    const bloqueEnviado = consultar(selector('enviado'));
    const avisoEnlace = consultar(selector('aviso-envio'));
    const toast = consultar(selector('toast'));
    const formatear = crearFormateador(raiz.dataset.moneda ?? 'MXN', raiz.dataset.locale ?? 'es-MX');
    const numero = raiz.dataset.numero ?? '';
    const clave = raiz.dataset.clave ?? claveDefecto;

    // Catálogo de la página: clave "producto:variante" -> datos que escribió el servidor.
    const catalogo = new Map();
    document.querySelectorAll(selector('agregar')).forEach((boton) => {
        const precio = centavos(boton.dataset.precio);
        const producto = Number.parseInt(boton.dataset.producto ?? '', 10);

        if (Number.isNaN(precio) || Number.isNaN(producto) || !boton.dataset.nombre) {
            return;
        }

        const variante = boton.dataset.variante ? Number.parseInt(boton.dataset.variante, 10) : 0;
        catalogo.set(`${producto}:${variante}`, { producto, variante, nombre: boton.dataset.nombre, nombreVariante: boton.dataset.nombreVariante ?? '', precio });
        boton.hidden = false;
    });

    // Con JavaScript el enlace sin pedido armado se sustituye por el carrito.
    document.querySelectorAll(selector('sin-js')).forEach((nodo) => {
        nodo.hidden = true;
    });

    let lineas = [];
    let temporizadorToast = null;

    const cargar = () => {
        try {
            const crudo = JSON.parse(localStorage.getItem(clave) ?? '[]');

            if (!Array.isArray(crudo)) {
                return [];
            }

            const vistos = new Set();

            return crudo.flatMap((linea) => {
                const producto = Number.parseInt(linea?.p, 10);
                const variante = linea?.v ? Number.parseInt(linea.v, 10) : 0;
                const llave = `${producto}:${variante}`;
                const cantidad = Number.parseInt(linea?.c, 10);

                if (!catalogo.has(llave) || vistos.has(llave) || !(cantidad >= 1)) {
                    return [];
                }

                vistos.add(llave);

                return [{ llave, cantidad: Math.min(cantidad, LIMITES.cantidad), nota: limpiarTexto(linea?.n, LIMITES.notaLinea) }];
            }).slice(0, LIMITES.lineas);
        } catch {
            return [];
        }
    };

    const guardar = () => {
        try {
            const datos = lineas.map((linea) => {
                const { producto, variante } = catalogo.get(linea.llave);

                return { p: producto, v: variante || null, c: linea.cantidad, n: linea.nota };
            });
            localStorage.setItem(clave, JSON.stringify(datos));
        } catch {
            // Sin almacenamiento el pedido sigue funcionando mientras la página esté abierta.
        }
    };

    const mostrarToast = (texto) => {
        toast.textContent = texto;
        toast.hidden = false;
        clearTimeout(temporizadorToast);
        temporizadorToast = setTimeout(() => {
            toast.hidden = true;
        }, 2500);
    };

    const datosDeLineas = () => lineas.map((linea) => {
        const articulo = catalogo.get(linea.llave);

        return { llave: linea.llave, cantidad: linea.cantidad, nombre: articulo.nombre, variante: articulo.nombreVariante, nota: linea.nota, precio: articulo.precio };
    });

    const pintar = (foco = null) => {
        const detalle = datosDeLineas();
        const cantidad = detalle.reduce((suma, linea) => suma + linea.cantidad, 0);
        const total = formatear(totalizar(detalle));

        barra.hidden = cantidad === 0;
        barra.querySelector(selector('cuenta')).textContent = String(cantidad);
        barra.querySelector(selector('barra-total')).textContent = total;
        barra.setAttribute('aria-label', `Ver pedido: ${cantidad} ${cantidad === 1 ? 'platillo' : 'platillos'}, total ${total}`);
        consultar(selector('total')).textContent = total;

        vacio.hidden = detalle.length > 0;
        botonEnviar.disabled = detalle.length === 0;
        botonVaciar.hidden = detalle.length === 0;
        lista.replaceChildren(...detalle.map((linea) => {
            const fila = plantilla.content.firstElementChild.cloneNode(true);
            const etiqueta = linea.variante ? `${linea.nombre} (${linea.variante})` : linea.nombre;

            fila.dataset.llave = linea.llave;
            fila.querySelector(selector('nombre')).textContent = etiqueta;
            fila.querySelector(selector('subtotal')).textContent = formatear(linea.cantidad * linea.precio);
            fila.querySelector(selector('cantidad')).textContent = String(linea.cantidad);

            const menos = fila.querySelector(selector('menos'));
            const mas = fila.querySelector(selector('mas-uno'));
            menos.setAttribute('aria-label', `Quitar uno de ${etiqueta}`);
            mas.setAttribute('aria-label', `Agregar uno más de ${etiqueta}`);
            mas.disabled = linea.cantidad >= LIMITES.cantidad;

            const nota = fila.querySelector(selector('nota'));
            nota.value = linea.nota;
            nota.maxLength = LIMITES.notaLinea;
            nota.setAttribute('aria-label', `Nota para ${etiqueta}`);

            return fila;
        }));

        if (foco) {
            const destino = lista.querySelector(`[data-llave="${CSS.escape(foco.llave)}"] [${foco.accion}]`) ?? lista.querySelector(selector('menos')) ?? consultar(selector('cerrar'));
            destino?.focus();
        }
    };

    const cambiar = (llave, delta) => {
        const linea = lineas.find((otra) => otra.llave === llave);

        if (!linea) {
            return;
        }

        linea.cantidad = Math.min(linea.cantidad + delta, LIMITES.cantidad);
        lineas = lineas.filter((otra) => otra.cantidad > 0);
        guardar();
        pintar({ llave, accion: delta < 0 ? atributo('menos') : atributo('mas-uno') });
    };

    const agregar = (boton) => {
        const llave = `${Number.parseInt(boton.dataset.producto, 10)}:${boton.dataset.variante ? Number.parseInt(boton.dataset.variante, 10) : 0}`;
        const articulo = catalogo.get(llave);
        const existente = lineas.find((linea) => linea.llave === llave);

        if (!articulo) {
            return;
        }

        if (existente) {
            if (existente.cantidad >= LIMITES.cantidad) {
                mostrarToast(`Máximo ${LIMITES.cantidad} por platillo.`);

                return;
            }

            existente.cantidad += 1;
        } else if (lineas.length >= LIMITES.lineas) {
            mostrarToast('Tu pedido ya tiene muchos platillos. Envíalo y haz otro.');

            return;
        } else {
            lineas.push({ llave, cantidad: 1, nota: '' });
        }

        guardar();
        pintar();
        mostrarToast(`${articulo.nombreVariante ? `${articulo.nombre} (${articulo.nombreVariante})` : articulo.nombre} agregado al pedido.`);
    };

    const reiniciarAvisos = () => {
        confirmarVaciar.hidden = true;
        bloqueEnviado.hidden = true;
        avisoEnlace.hidden = true;
        avisoEnlace.textContent = '';
    };

    const abrir = () => {
        reiniciarAvisos();

        if (typeof dialogo.showModal === 'function') {
            dialogo.showModal();
        } else {
            dialogo.setAttribute('open', '');
        }

        barra.setAttribute('aria-expanded', 'true');
    };

    const cerrar = () => {
        if (typeof dialogo.close === 'function') {
            dialogo.close();
        } else {
            dialogo.removeAttribute('open');
        }
    };

    dialogo.addEventListener('close', () => {
        reiniciarAvisos();
        barra.setAttribute('aria-expanded', 'false');
        (barra.hidden ? document.querySelector(selector('agregar')) : barra)?.focus();
    });

    const enviar = () => {
        const detalle = datosDeLineas();

        if (detalle.length === 0) {
            return;
        }

        campoMesa.value = limpiarMesa(campoMesa.value);
        const resultado = armarEnlace({ numero, restaurante: raiz.dataset.restaurante ?? '', mesa: campoMesa.value, notaCocina: campoCocina.value, lineas: detalle, formatear });

        if (resultado === null) {
            mostrarToast('No pudimos armar el pedido. Escríbenos por WhatsApp desde el contacto.');

            return;
        }

        const avisos = [];

        if (resultado.omitidas > 0) {
            avisos.push(`Tu pedido es largo y el mensaje no incluye ${resultado.omitidas} ${resultado.omitidas === 1 ? 'platillo' : 'platillos'}. Dínoslo en el chat de WhatsApp.`);
        }

        if (resultado.sinNotaCocina) {
            avisos.push('La nota para la cocina no cupo en el mensaje. Escríbela de nuevo en el chat.');
        }

        avisoEnlace.textContent = avisos.join(' ');
        avisoEnlace.hidden = avisos.length === 0;
        window.open(resultado.url, '_blank', 'noopener,noreferrer');
        bloqueEnviado.hidden = false;
        bloqueEnviado.focus();
    };

    const vaciar = () => {
        lineas = [];
        guardar();
        pintar();
    };

    barra.addEventListener('click', abrir);
    dialogo.addEventListener('click', (evento) => {
        if (evento.target === dialogo) {
            cerrar();

            return;
        }

        const boton = evento.target.closest('button');

        if (!boton) {
            return;
        }

        const fila = boton.closest('[data-llave]');

        if (boton.hasAttribute(atributo('cerrar'))) {
            cerrar();
        } else if (boton.hasAttribute(atributo('menos')) && fila) {
            cambiar(fila.dataset.llave, -1);
        } else if (boton.hasAttribute(atributo('mas-uno')) && fila) {
            cambiar(fila.dataset.llave, 1);
        } else if (boton.hasAttribute(atributo('enviar'))) {
            enviar();
        } else if (boton.hasAttribute(atributo('vaciar'))) {
            confirmarVaciar.hidden = false;
            confirmarVaciar.querySelector(selector('conservar')).focus();
        } else if (boton.hasAttribute(atributo('conservar'))) {
            confirmarVaciar.hidden = true;
            botonVaciar.focus();
        } else if (boton.hasAttribute(atributo('confirmar'))) {
            confirmarVaciar.hidden = true;
            vaciar();
            mostrarToast('Vaciaste tu pedido.');
            consultar(selector('cerrar')).focus();
        } else if (boton.hasAttribute(atributo('listo'))) {
            vaciar();
            campoCocina.value = '';
            cerrar();
            mostrarToast('Listo. Vaciamos tu pedido.');
        } else if (boton.hasAttribute(atributo('aun-no'))) {
            bloqueEnviado.hidden = true;
            botonEnviar.focus();
        }
    });

    dialogo.addEventListener('input', (evento) => {
        const campo = evento.target.closest(selector('nota'));
        const fila = campo?.closest('[data-llave]');
        const linea = fila ? lineas.find((otra) => otra.llave === fila.dataset.llave) : null;

        if (linea) {
            linea.nota = limpiarTexto(campo.value, LIMITES.notaLinea);
            guardar();
        }
    });

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest(selector('agregar'));

        if (boton && !dialogo.open) {
            agregar(boton);
        }
    });

    campoMesa.value = limpiarMesa(raiz.dataset.mesa ?? '');
    campoMesa.maxLength = LIMITES.mesa;
    campoCocina.maxLength = LIMITES.notaCocina;

    lineas = cargar();
    guardar();
    pintar();
}
