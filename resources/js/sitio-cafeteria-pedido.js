// Pedido por WhatsApp del menú de la plantilla E (Cafetería). Sin dependencias.
//
// - Nombres, precios, número, moneda y mesa salen de atributos de datos que escribe el servidor; nada de lo que escribe el cliente
//   define un precio o un nombre.
// - localStorage guarda solo identificadores, cantidades y notas. Cada carga se vuelve a contrastar con los platillos de la página:
//   lo que ya no existe o está agotado se descarta.
// - Todo el contenido se inserta con textContent y nodos del DOM; no hay HTML armado con texto ni manejadores en línea.
// - El formato del mensaje vive en sitio-cafeteria-pedido-mensaje.js y debe mantenerse igual al de menu.publico.
import { LIMITES, armarEnlace, centavos, crearFormateador, limpiarMesa, limpiarTexto, totalizar } from './sitio-cafeteria-pedido-mensaje.js';

const raiz = document.querySelector('[data-cal-pedido]');

if (raiz) {
    const consultar = (selector) => raiz.querySelector(selector);
    const dialogo = consultar('[data-cal-dialogo]');
    const barra = consultar('[data-cal-barra]');
    const lista = consultar('[data-cal-lineas]');
    const plantilla = consultar('template[data-cal-plantilla-linea]');
    const vacio = consultar('[data-cal-vacio]');
    const campoMesa = consultar('[data-cal-mesa]');
    const campoCocina = consultar('[data-cal-cocina]');
    const botonEnviar = consultar('[data-cal-enviar]');
    const botonVaciar = consultar('[data-cal-vaciar]');
    const confirmarVaciar = consultar('[data-cal-confirmar-vaciar]');
    const bloqueEnviado = consultar('[data-cal-enviado]');
    const avisoEnlace = consultar('[data-cal-aviso-envio]');
    const toast = consultar('[data-cal-toast]');
    const formatear = crearFormateador(raiz.dataset.moneda ?? 'MXN', raiz.dataset.locale ?? 'es-MX');
    const numero = raiz.dataset.numero ?? '';
    const clave = raiz.dataset.clave ?? 'menuonl_cart_e';

    // Catálogo de la página: clave "producto:variante" -> datos que escribió el servidor.
    const catalogo = new Map();
    document.querySelectorAll('[data-cal-agregar]').forEach((boton) => {
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
    document.querySelectorAll('[data-cal-sin-js]').forEach((nodo) => {
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
        barra.querySelector('[data-cal-cuenta]').textContent = String(cantidad);
        barra.querySelector('[data-cal-barra-total]').textContent = total;
        barra.setAttribute('aria-label', `Ver pedido: ${cantidad} ${cantidad === 1 ? 'platillo' : 'platillos'}, total ${total}`);
        consultar('[data-cal-total]').textContent = total;

        vacio.hidden = detalle.length > 0;
        botonEnviar.disabled = detalle.length === 0;
        botonVaciar.hidden = detalle.length === 0;
        lista.replaceChildren(...detalle.map((linea) => {
            const fila = plantilla.content.firstElementChild.cloneNode(true);
            const etiqueta = linea.variante ? `${linea.nombre} (${linea.variante})` : linea.nombre;

            fila.dataset.llave = linea.llave;
            fila.querySelector('[data-cal-nombre]').textContent = etiqueta;
            fila.querySelector('[data-cal-subtotal]').textContent = formatear(linea.cantidad * linea.precio);
            fila.querySelector('[data-cal-cantidad]').textContent = String(linea.cantidad);

            const menos = fila.querySelector('[data-cal-menos]');
            const mas = fila.querySelector('[data-cal-mas-uno]');
            menos.setAttribute('aria-label', `Quitar uno de ${etiqueta}`);
            mas.setAttribute('aria-label', `Agregar uno más de ${etiqueta}`);
            mas.disabled = linea.cantidad >= LIMITES.cantidad;

            const nota = fila.querySelector('[data-cal-nota]');
            nota.value = linea.nota;
            nota.maxLength = LIMITES.notaLinea;
            nota.setAttribute('aria-label', `Nota para ${etiqueta}`);

            return fila;
        }));

        if (foco) {
            const destino = lista.querySelector(`[data-llave="${CSS.escape(foco.llave)}"] [${foco.accion}]`) ?? lista.querySelector('[data-cal-menos]') ?? consultar('[data-cal-cerrar]');
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
        pintar({ llave, accion: delta < 0 ? 'data-cal-menos' : 'data-cal-mas-uno' });
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
        (barra.hidden ? document.querySelector('[data-cal-agregar]') : barra)?.focus();
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

        if (boton.hasAttribute('data-cal-cerrar')) {
            cerrar();
        } else if (boton.hasAttribute('data-cal-menos') && fila) {
            cambiar(fila.dataset.llave, -1);
        } else if (boton.hasAttribute('data-cal-mas-uno') && fila) {
            cambiar(fila.dataset.llave, 1);
        } else if (boton.hasAttribute('data-cal-enviar')) {
            enviar();
        } else if (boton.hasAttribute('data-cal-vaciar')) {
            confirmarVaciar.hidden = false;
            confirmarVaciar.querySelector('[data-cal-conservar]').focus();
        } else if (boton.hasAttribute('data-cal-conservar')) {
            confirmarVaciar.hidden = true;
            botonVaciar.focus();
        } else if (boton.hasAttribute('data-cal-confirmar')) {
            confirmarVaciar.hidden = true;
            vaciar();
            mostrarToast('Vaciaste tu pedido.');
            consultar('[data-cal-cerrar]').focus();
        } else if (boton.hasAttribute('data-cal-listo')) {
            vaciar();
            campoCocina.value = '';
            cerrar();
            mostrarToast('Listo. Vaciamos tu pedido.');
        } else if (boton.hasAttribute('data-cal-aun-no')) {
            bloqueEnviado.hidden = true;
            botonEnviar.focus();
        }
    });

    dialogo.addEventListener('input', (evento) => {
        const campo = evento.target.closest('[data-cal-nota]');
        const fila = campo?.closest('[data-llave]');
        const linea = fila ? lineas.find((otra) => otra.llave === fila.dataset.llave) : null;

        if (linea) {
            linea.nota = limpiarTexto(campo.value, LIMITES.notaLinea);
            guardar();
        }
    });

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-cal-agregar]');

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
