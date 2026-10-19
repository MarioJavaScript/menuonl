/*
 * Comportamiento del prototipo del sitio (/sitio/{slug}). Sin librerías.
 *  1. Aparición suave de secciones (se omite con prefers-reduced-motion).
 *  2. Cierre del menú móvil al elegir una sección.
 *  3. Formulario de reservación: valida en el navegador y abre WhatsApp con el mensaje armado.
 * Los datos del restaurante se leen de atributos data- del formulario; nunca de código en línea.
 */

const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- 1. Aparición al hacer scroll ---------- */
function iniciarRevelado() {
    const elementos = document.querySelectorAll('.revelar');

    if (sinMovimiento || !('IntersectionObserver' in window)) {
        return;
    }

    document.documentElement.classList.add('con-js');

    const observador = new IntersectionObserver(
        (entradas) => {
            entradas.forEach((entrada) => {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visto');
                    observador.unobserve(entrada.target);
                }
            });
        },
        { threshold: 0.12 },
    );

    elementos.forEach((elemento) => observador.observe(elemento));
}

/* ---------- 2. Menú móvil ---------- */
function iniciarMenuMovil() {
    const menu = document.querySelector('[data-menu-movil]');
    if (!menu) {
        return;
    }
    menu.querySelectorAll('a').forEach((enlace) => {
        enlace.addEventListener('click', () => menu.removeAttribute('open'));
    });
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && menu.hasAttribute('open')) {
            menu.removeAttribute('open');
            menu.querySelector('summary')?.focus();
        }
    });
}

/* ---------- 3. Reservación por WhatsApp ---------- */
const MAX_DIAS_ANTICIPACION = 90;

function dosDigitos(numero) {
    return String(numero).padStart(2, '0');
}

function fechaLocalISO(fecha) {
    return `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}-${dosDigitos(fecha.getDate())}`;
}

function aMinutos(hhmm) {
    const [horas, minutos] = hhmm.split(':').map(Number);
    return horas * 60 + minutos;
}

/** Lee los turnos del restaurante (data-horarios) sin fallar si el JSON no es válido. */
function leerTurnos(formulario) {
    try {
        const turnos = JSON.parse(formulario.dataset.horarios || '[]');
        return Array.isArray(turnos) ? turnos : [];
    } catch {
        return [];
    }
}

/**
 * Indica si la hora cae dentro de algún turno del día (incluye turnos que cruzan la medianoche).
 * Sin turnos configurados no se bloquea nada.
 */
function horaDentroDeHorario(turnos, diaSemana, hora) {
    if (turnos.length === 0) {
        return { valido: true };
    }

    const minuto = aMinutos(hora);
    const ayer = (diaSemana + 6) % 7;

    const dentro = turnos.some((turno) => {
        const abre = aMinutos(turno.abre);
        const cierra = aMinutos(turno.cierra);
        const cruzaMedianoche = cierra <= abre;

        if (turno.dia === diaSemana) {
            return cruzaMedianoche ? minuto >= abre : minuto >= abre && minuto <= cierra;
        }

        return turno.dia === ayer && cruzaMedianoche && minuto <= cierra;
    });

    return { valido: dentro };
}

function mostrarError(formulario, campo, mensaje) {
    const entrada = formulario.elements[campo];
    const aviso = document.getElementById(`error-${campo}`);
    if (!entrada || !aviso) {
        return;
    }
    aviso.textContent = mensaje;
    aviso.hidden = false;
    entrada.setAttribute('aria-invalid', 'true');
}

function limpiarErrores(formulario) {
    formulario.querySelectorAll('[aria-invalid]').forEach((entrada) => entrada.removeAttribute('aria-invalid'));
    formulario.querySelectorAll('.error-campo').forEach((aviso) => {
        aviso.textContent = '';
        aviso.hidden = true;
    });
}

/** Devuelve { campo: mensaje } con los errores encontrados (vacío si todo está bien). */
function validar(formulario, turnos) {
    const errores = {};
    const valor = (campo) => formulario.elements[campo].value.trim();

    if (valor('nombre').length < 2) {
        errores.nombre = 'Escribe tu nombre (mínimo 2 letras).';
    }

    const telefono = valor('telefono');
    const digitos = telefono.replace(/[\s()+-]/g, '');
    if (!/^\d{8,15}$/.test(digitos)) {
        errores.telefono = 'Escribe un teléfono válido de 8 a 15 dígitos.';
    }

    const fecha = valor('fecha');
    const hoy = new Date();
    const hoyISO = fechaLocalISO(hoy);
    const limite = new Date(hoy);
    limite.setDate(limite.getDate() + MAX_DIAS_ANTICIPACION);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        errores.fecha = 'Elige la fecha de tu visita.';
    } else if (fecha < hoyISO) {
        errores.fecha = 'La fecha no puede ser anterior a hoy.';
    } else if (fecha > fechaLocalISO(limite)) {
        errores.fecha = `Reserva con un máximo de ${MAX_DIAS_ANTICIPACION} días de anticipación.`;
    }

    const hora = valor('hora');
    if (!/^\d{2}:\d{2}$/.test(hora)) {
        errores.hora = 'Elige la hora de tu visita.';
    } else if (!errores.fecha) {
        const [anio, mes, dia] = fecha.split('-').map(Number);
        const diaSemana = new Date(anio, mes - 1, dia).getDay();
        const ahoraMin = hoy.getHours() * 60 + hoy.getMinutes();

        if (fecha === hoyISO && aMinutos(hora) <= ahoraMin) {
            errores.hora = 'Esa hora ya pasó. Elige una más tarde.';
        } else if (!horaDentroDeHorario(turnos, diaSemana, hora).valido) {
            errores.hora = 'Esa hora está fuera del horario del restaurante. Revisa los horarios más abajo.';
        }
    }

    const personas = Number(valor('personas'));
    if (!Number.isInteger(personas) || personas < 1 || personas > 12) {
        errores.personas = 'Elige de 1 a 12 personas.';
    }

    if (valor('notas').length > 300) {
        errores.notas = 'Las notas pueden tener hasta 300 caracteres.';
    }

    return errores;
}

function componerMensaje(formulario) {
    const valor = (campo) => formulario.elements[campo].value.trim();
    const [anio, mes, dia] = valor('fecha').split('-');
    const lineas = [
        `Hola, quiero reservar una mesa en ${formulario.dataset.restaurante}.`,
        `Nombre: ${valor('nombre')}`,
        `Teléfono: ${valor('telefono')}`,
        `Fecha: ${dia}/${mes}/${anio}`,
        `Hora: ${valor('hora')}`,
        `Personas: ${valor('personas')}`,
    ];
    if (valor('notas') !== '') {
        lineas.push(`Notas: ${valor('notas')}`);
    }
    return lineas.join('\n');
}

function iniciarReservacion() {
    const formulario = document.querySelector('[data-reserva]');
    if (!formulario) {
        return;
    }

    const turnos = leerTurnos(formulario);
    const estado = document.getElementById('estado-reserva');
    const fecha = formulario.elements.fecha;
    const hoy = new Date();
    const limite = new Date(hoy);
    limite.setDate(limite.getDate() + MAX_DIAS_ANTICIPACION);
    fecha.min = fechaLocalISO(hoy);
    fecha.max = fechaLocalISO(limite);

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        limpiarErrores(formulario);
        if (estado) {
            estado.hidden = true;
        }

        const errores = validar(formulario, turnos);
        const campos = Object.keys(errores);

        if (campos.length > 0) {
            campos.forEach((campo) => mostrarError(formulario, campo, errores[campo]));
            formulario.elements[campos[0]].focus();
            return;
        }

        const numero = (formulario.dataset.whatsapp || '').replace(/\D/g, '');
        if (numero === '') {
            return;
        }

        const url = `https://wa.me/${numero}?text=${encodeURIComponent(componerMensaje(formulario))}`;
        window.open(url, '_blank', 'noopener');

        if (estado) {
            estado.textContent = 'Listo: se abrió WhatsApp con tu mensaje. Envíalo para completar la solicitud.';
            estado.hidden = false;
        }
    });
}

iniciarRevelado();
iniciarMenuMovil();
iniciarReservacion();
