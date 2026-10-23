// Presentación del cajón del pedido de menu.publico para pedido/controlador.js: fondo + hoja con las clases open/cerrando, trampa de foco,
// Escape, bloqueo del scroll y cierre al arrastrar (el evento hoja-cerrar lo dispara el componente "hoja" de Alpine en menu.js).
// Es el comportamiento que tenía el script en línea de la vista; el controlador solo pide abrir, cerrar, estaAbierto y mostrarToast.

/**
 * @param {{dialogo: HTMLElement, barra: HTMLElement, alCerrar: () => void}} contexto
 */
export function crearPresentacionHoja({ dialogo, barra, alCerrar }) {
    let temporizadorToast = null;
    const sinMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const estaAbierto = () => dialogo.classList.contains('open');

    const abrir = () => {
        dialogo.classList.remove('cerrando');
        dialogo.classList.add('open');
        document.body.style.overflow = 'hidden';
        document.getElementById('btnCloseCartModal')?.focus();
    };

    const cerrar = (inmediato = false) => {
        const terminar = () => {
            dialogo.classList.remove('open', 'cerrando');
        };

        if (estaAbierto() && !inmediato && !sinMovimiento()) {
            dialogo.classList.add('cerrando');
            setTimeout(terminar, 200);
        } else {
            terminar();
        }

        document.body.style.overflow = '';
        alCerrar();
    };

    const mostrarToast = (mensaje) => {
        const toast = document.getElementById('cartToast');
        const texto = document.getElementById('cartToastMessage');

        if (!toast || !texto) {
            return;
        }

        texto.textContent = mensaje;
        toast.classList.add('visible');
        clearTimeout(temporizadorToast);
        temporizadorToast = setTimeout(() => {
            toast.classList.remove('visible');
        }, 2500);
    };

    dialogo.addEventListener('hoja-cerrar', () => cerrar(true));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Tab' && estaAbierto()) {
            const foco = Array.from(dialogo.querySelectorAll('button, input, textarea')).filter((el) => !el.disabled && el.offsetParent !== null);

            if (foco.length) {
                const primero = foco[0];
                const ultimo = foco[foco.length - 1];

                if (e.shiftKey && (document.activeElement === primero || !dialogo.contains(document.activeElement))) {
                    e.preventDefault();
                    ultimo.focus();
                } else if (!e.shiftKey && (document.activeElement === ultimo || !dialogo.contains(document.activeElement))) {
                    e.preventDefault();
                    primero.focus();
                }
            }
        }

        if ((e.key === 'Escape' || e.key === 'Esc') && estaAbierto()) {
            cerrar();
        }
    });

    return { abrir, cerrar, estaAbierto, mostrarToast };
}
