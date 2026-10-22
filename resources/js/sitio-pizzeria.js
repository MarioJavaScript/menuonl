// Menú de la plantilla H (Pizzería): filtro por categoría. Sin dependencias.
// Sin JavaScript todas las categorías quedan visibles, una debajo de otra.
const contenedor = document.querySelector('[data-piz-filtros]');

if (contenedor) {
    const grupos = Array.from(document.querySelectorAll('[data-piz-grupo]'));
    const aviso = document.querySelector('[data-piz-aviso]');

    contenedor.hidden = false;
    contenedor.addEventListener('click', (evento) => {
        const boton = evento.target.closest('button[data-piz-filtro]');

        if (!boton) {
            return;
        }

        const categoria = boton.dataset.pizFiltro;

        contenedor.querySelectorAll('button').forEach((otro) => {
            otro.setAttribute('aria-pressed', String(otro === boton));
        });
        grupos.forEach((grupo) => {
            grupo.hidden = categoria !== '' && grupo.dataset.pizGrupo !== categoria;
        });

        if (aviso) {
            aviso.textContent = categoria === '' ? 'Mostrando todas las categorías.' : `Mostrando ${boton.textContent.trim()}.`;
        }
    });
}
