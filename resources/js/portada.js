import { animate, inView, scroll, stagger } from 'motion';

/* Animaciones de la portada. El CSS deja todo visible; la clase "anim" (puesta en <html> antes de pintar,
   solo si la persona no pidió menos movimiento) es la que oculta los elementos hasta que entran en pantalla. */
const reducido = matchMedia('(prefers-reduced-motion: reduce)').matches;
window.__portadaLista = true;

if (!reducido) {
    document.querySelectorAll('[data-grupo]').forEach((grupo) => {
        const hijos = grupo.querySelectorAll('[data-revela]');
        inView(grupo, () => {
            animate(hijos, { opacity: [0, 1], y: [28, 0], scale: [0.97, 1] }, { duration: 0.7, delay: stagger(0.09), ease: [0.22, 1, 0.36, 1] });
        }, { amount: 0.2 });
    });

    document.querySelectorAll('[data-revela]:not([data-grupo] [data-revela])').forEach((el) => {
        inView(el, () => {
            animate(el, { opacity: [0, 1], y: [28, 0] }, { duration: 0.8, ease: [0.22, 1, 0.36, 1] });
        }, { amount: 0.25 });
    });

    document.querySelectorAll('[data-parallax]').forEach((el) => {
        const fuerza = Number(el.dataset.parallax) || 40;
        scroll(animate(el, { y: [fuerza, -fuerza] }, { ease: 'linear' }), { target: el, offset: ['start end', 'end start'] });
    });

    document.querySelectorAll('[data-cuenta]').forEach((el) => {
        const meta = Number(el.dataset.cuenta);
        inView(el, () => {
            animate(0, meta, { duration: 1.4, ease: 'easeOut', onUpdate: (valor) => { el.textContent = Math.round(valor); } });
        }, { amount: 0.6 });
    });

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
