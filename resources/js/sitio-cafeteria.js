// Menú de la plantilla E (Cafetería): filtro por categoría, carrusel de dos filas y botón "Más". Sin dependencias.
// Sin JavaScript todos los platillos quedan visibles y se recorren con el desplazamiento nativo.
const pista = document.querySelector('[data-cal-pista]');

if (pista) {
    const LIMITE_INICIAL = 8;
    const platos = Array.from(pista.querySelectorAll('[data-cal-plato]'));
    const contenedorCategorias = document.querySelector('[data-cal-categorias]');
    const botonMas = document.querySelector('[data-cal-mas]');
    const aviso = document.querySelector('[data-cal-aviso]');
    const flechas = Array.from(document.querySelectorAll('[data-cal-flecha]'));
    const reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    const estado = { categoria: 'todas', expandido: false };

    const actualizarFlechas = () => {
        const maximo = pista.scrollWidth - pista.clientWidth - 2;

        flechas.forEach((flecha) => {
            const direccion = Number(flecha.dataset.calFlecha);
            const apagada = direccion < 0 ? pista.scrollLeft <= 2 : pista.scrollLeft >= maximo;
            flecha.setAttribute('aria-disabled', String(apagada));
        });
    };

    const pintar = () => {
        const visibles = platos.filter((plato) => estado.categoria === 'todas' || plato.dataset.categoria === estado.categoria);
        const hayMas = visibles.length > LIMITE_INICIAL;
        const mostrados = hayMas && !estado.expandido ? visibles.slice(0, LIMITE_INICIAL) : visibles;

        platos.forEach((plato) => {
            plato.hidden = !mostrados.includes(plato);
        });
        pista.scrollLeft = 0;

        botonMas.hidden = !hayMas;
        botonMas.textContent = estado.expandido ? 'Menos' : 'Más';
        botonMas.setAttribute('aria-expanded', String(estado.expandido));
        aviso.textContent = `Mostrando ${mostrados.length} de ${visibles.length} platillos.`;
        actualizarFlechas();
    };

    if (contenedorCategorias) {
        contenedorCategorias.hidden = false;
        contenedorCategorias.addEventListener('click', (evento) => {
            const boton = evento.target.closest('button[data-categoria]');

            if (!boton) {
                return;
            }

            estado.categoria = boton.dataset.categoria;
            estado.expandido = false;
            contenedorCategorias.querySelectorAll('button').forEach((otro) => {
                otro.setAttribute('aria-pressed', String(otro === boton));
            });
            pintar();
        });
    }

    flechas.forEach((flecha) => {
        flecha.hidden = false;
        flecha.addEventListener('click', () => {
            pista.scrollBy({ left: Number(flecha.dataset.calFlecha) * pista.clientWidth * 0.85, behavior: reducirMovimiento.matches ? 'auto' : 'smooth' });
        });
    });

    let pendiente = false;
    pista.addEventListener('scroll', () => {
        if (pendiente) {
            return;
        }

        pendiente = true;
        requestAnimationFrame(() => {
            pendiente = false;
            actualizarFlechas();
        });
    }, { passive: true });
    window.addEventListener('resize', actualizarFlechas);

    botonMas.addEventListener('click', () => {
        estado.expandido = !estado.expandido;
        pintar();
    });

    pintar();
}
