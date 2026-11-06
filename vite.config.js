import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/menu.js', 'resources/js/menu-pedido.js', 'resources/css/sitio/b.css', 'resources/css/sitio/d.css', 'resources/css/sitio/e.css', 'resources/js/sitio-cafeteria.js', 'resources/js/sitio-cafeteria-pedido.js', 'resources/css/sitio/f.css', 'resources/js/sitio-bar.js', 'resources/js/sitio-bar-pedido.js', 'resources/css/sitio/g.css', 'resources/js/sitio-pastel.js', 'resources/js/sitio-pastel-pedido.js', 'resources/css/sitio/h.css', 'resources/js/sitio-pizzeria.js', 'resources/js/sitio-pizzeria-pedido.js', 'resources/css/sitio/i.css', 'resources/js/sitio-japones.js', 'resources/js/sitio-japones-pedido.js', 'resources/css/sitio/j.css', 'resources/js/sitio-mariscos.js', 'resources/js/sitio-mariscos-pedido.js', 'resources/css/sitio/k.css', 'resources/js/sitio-cortes.js', 'resources/js/sitio-cortes-pedido.js', 'resources/css/sitio/l.css', 'resources/js/sitio-esmeralda.js', 'resources/js/sitio-esmeralda-pedido.js', 'resources/css/sitio/m.css', 'resources/js/sitio-hamburguesas.js', 'resources/js/sitio-hamburguesas-pedido.js', 'resources/css/sitio/n.css', 'resources/js/sitio-mexicana.js', 'resources/js/sitio-mexicana-pedido.js', 'resources/js/sitio.js', 'resources/css/filament/admin/theme.css', 'resources/css/landing.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
