// Menú de la plantilla F (Bar): filtro por categoría. Sin dependencias.
// Sin JavaScript todas las categorías quedan visibles, una debajo de otra.
const contenedor = document.querySelector('[data-bar-filtros]');

if (contenedor) {
    const grupos = Array.from(document.querySelectorAll('[data-bar-grupo]'));
    const aviso = document.querySelector('[data-bar-aviso]');

    contenedor.hidden = false;
    contenedor.addEventListener('click', (evento) => {
        const boton = evento.target.closest('button[data-bar-filtro]');

        if (!boton) {
            return;
        }

        const categoria = boton.dataset.barFiltro;

        contenedor.querySelectorAll('button').forEach((otro) => {
            otro.setAttribute('aria-pressed', String(otro === boton));
        });
        grupos.forEach((grupo) => {
            grupo.hidden = categoria !== '' && grupo.dataset.barGrupo !== categoria;
        });

        if (aviso) {
            aviso.textContent = categoria === '' ? 'Mostrando todas las categorías.' : `Mostrando ${boton.textContent.trim()}.`;
        }
    });
}
