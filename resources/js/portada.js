import { animate, inView, scroll, stagger, spring } from 'motion';

/* Animaciones de la portada. El CSS deja todo visible; la clase "anim" (puesta en <html> antes de pintar,
   solo si la persona no pidió menos movimiento) oculta los bloques hasta que Motion los revela. */
const reducido = matchMedia('(prefers-reduced-motion: reduce)').matches;
window.__portadaLista = true;

const suave = [0.22, 1, 0.36, 1];

if (!reducido) {
    /* Los títulos entran palabra por palabra. */
    document.querySelectorAll('[data-palabras]').forEach((titulo) => {
        const palabras = titulo.textContent.trim().split(/\s+/);
        titulo.setAttribute('aria-label', titulo.textContent.trim());
        titulo.innerHTML = palabras.map((p) => `<span class="w" aria-hidden="true">${p}</span>`).join(' ');
        const piezas = titulo.querySelectorAll('.w');
        piezas.forEach((pieza) => { pieza.style.opacity = '0'; });
        inView(titulo, () => {
            animate(piezas, { opacity: [0, 1], y: [34, 0], rotate: [3, 0] }, { duration: 0.8, delay: stagger(0.07), ease: suave });
        }, { amount: 0.6 });
    });

    /* Entrada del hero: líneas del titular, imagen y tarjetas con rebote. */
    const lineas = document.querySelectorAll('.pt-hero h1 .pt-linea > span');
    animate(lineas, { y: ['108%', '0%'] }, { duration: 1, delay: stagger(0.12, { startDelay: 0.1 }), ease: suave });
    const botones = document.querySelector('.pt-hero .pt-acciones[data-entra]');
    if (botones) {
        animate(botones, { opacity: [0, 1], y: [24, 0] }, { duration: 0.8, delay: 0.55, ease: suave });
    }
    const marco = document.querySelector('.pt-marco[data-entra]');
    if (marco) {
        animate(marco, { opacity: [0, 1], scale: [0.88, 1], rotate: [-4, 0] }, { duration: 1.2, delay: 0.2, ease: suave });
    }
    document.querySelectorAll('.pt-flot[data-entra]').forEach((tarjeta, i) => {
        animate(tarjeta, { opacity: [0, 1], scale: [0.6, 1] }, { delay: 0.7 + i * 0.14, type: spring, stiffness: 160, damping: 13 });
    });

    /* Profundidad con el mouse: cada tarjeta se desplaza según su capa. */
    const escena = document.querySelector('[data-escena]');
    if (escena && matchMedia('(pointer: fine)').matches) {
        const capas = escena.querySelectorAll('[data-profundidad]');
        escena.addEventListener('pointermove', (evento) => {
            const caja = escena.getBoundingClientRect();
            const x = (evento.clientX - caja.left) / caja.width - 0.5;
            const y = (evento.clientY - caja.top) / caja.height - 0.5;
            capas.forEach((capa) => {
                const f = Number(capa.dataset.profundidad);
                capa.style.translate = `${(x * f).toFixed(1)}px ${(y * f).toFixed(1)}px`;
            });
        });
        escena.addEventListener('pointerleave', () => {
            capas.forEach((capa) => { capa.style.translate = '0 0'; });
        });
    }

    /* Barra de progreso de lectura en la cabecera. */
    const progreso = document.querySelector('.pt-progreso');
    if (progreso) {
        scroll(animate(progreso, { scaleX: [0, 1] }, { ease: 'linear' }));
    }

    /* Revelados escalonados por grupo y sueltos. */
    document.querySelectorAll('[data-grupo]').forEach((grupo) => {
        const hijos = grupo.querySelectorAll('[data-revela]');
        inView(grupo, () => {
            animate(hijos, { opacity: [0, 1], y: [34, 0], scale: [0.96, 1] }, { duration: 0.75, delay: stagger(0.09), ease: suave });
        }, { amount: 0.2 });
    });
    document.querySelectorAll('[data-revela]:not([data-grupo] [data-revela])').forEach((el) => {
        inView(el, () => {
            animate(el, { opacity: [0, 1], y: [28, 0] }, { duration: 0.8, ease: suave });
        }, { amount: 0.25 });
    });

    /* Línea que une los tres pasos, dibujada con el scroll. */
    document.querySelectorAll('[data-trazo]').forEach((linea) => {
        scroll(animate(linea, { scaleX: [0, 1] }, { ease: 'linear' }), { target: linea, offset: ['start 85%', 'start 45%'] });
    });

    /* Parallax con el scroll. */
    document.querySelectorAll('[data-parallax]').forEach((el) => {
        const fuerza = Number(el.dataset.parallax) || 40;
        scroll(animate(el, { y: [fuerza, -fuerza] }, { ease: 'linear' }), { target: el, offset: ['start end', 'end start'] });
    });

    /* Cifras que cuentan. */
    document.querySelectorAll('[data-cuenta]').forEach((el) => {
        const meta = Number(el.dataset.cuenta);
        inView(el, () => {
            animate(0, meta, { duration: 1.6, ease: 'easeOut', onUpdate: (valor) => { el.textContent = Math.round(valor); } });
        }, { amount: 0.6 });
    });

    /* Inclinación 3D al pasar el mouse. */
    document.querySelectorAll('[data-inclina]').forEach((el) => {
        const maximo = 7;
        el.addEventListener('pointermove', (evento) => {
            if (evento.pointerType !== 'mouse') {
                return;
            }
            const caja = el.getBoundingClientRect();
            const x = (evento.clientX - caja.left) / caja.width - 0.5;
            const y = (evento.clientY - caja.top) / caja.height - 0.5;
            el.style.setProperty('--rx', `${(-y * maximo).toFixed(2)}deg`);
            el.style.setProperty('--ry', `${(x * maximo).toFixed(2)}deg`);
        });
        el.addEventListener('pointerleave', () => {
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        });
    });
}
