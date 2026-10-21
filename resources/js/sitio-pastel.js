// Menú de la plantilla G (CafePastel): filtro por categoría. Sin dependencias.
// Sin JavaScript todas las categorías quedan visibles, una debajo de otra.
const contenedor = document.querySelector('[data-pas-filtros]');

if (contenedor) {
    const grupos = Array.from(document.querySelectorAll('[data-pas-grupo]'));
    const aviso = document.querySelector('[data-pas-aviso]');

    contenedor.hidden = false;
    contenedor.addEventListener('click', (evento) => {
        const boton = evento.target.closest('button[data-pas-filtro]');

        if (!boton) {
            return;
        }

        const categoria = boton.dataset.pasFiltro;

        contenedor.querySelectorAll('button').forEach((otro) => {
            otro.setAttribute('aria-pressed', String(otro === boton));
        });
        grupos.forEach((grupo) => {
            grupo.hidden = categoria !== '' && grupo.dataset.pasGrupo !== categoria;
        });

        if (aviso) {
            aviso.textContent = categoria === '' ? 'Mostrando todas las categorías.' : `Mostrando ${boton.textContent.trim()}.`;
        }
    });
}
