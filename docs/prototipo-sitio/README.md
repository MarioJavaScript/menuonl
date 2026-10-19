# Prototipo de sitio del restaurante

Sitio de una sola página para un restaurante, alimentado con los datos reales del sistema (nombre, categorías,
platillos, precios, horarios, contactos y estado abierto/cerrado en tiempo real). Es un experimento de diseño:
no está en producción ni toca la base de datos, las migraciones ni el menú público actual.

## Cómo verlo

1. Compilar los estilos (una vez, o dejar `npm run dev` / `composer run dev` corriendo): `npm run build`.
2. Tener el restaurante demo sembrado (`php artisan db:seed --class=DemoRestauranteSeeder`).
3. Abrir `/sitio/la-esquina` en el servidor local.

La ruta solo se registra con `APP_ENV=local` o `testing` (ver `routes/web.php`). En producción devuelve 404.
Si aún no se ha compilado Vite, la página carga sin estilos en lugar de fallar.

## Por qué Blade + Tailwind CSS v4

- Ya están instalados y son la pila del proyecto: cero dependencias nuevas.
- Los datos reales viven en Eloquent; Blade los renderiza en el servidor, sin API ni estado en el cliente.
- Página ligera para teléfonos modestos: HTML estático, un CSS de unos 9 KB comprimido y un JS de menos de 2 KB.
- Sin React, Vue ni Astro: no aportan nada a una página de lectura con un formulario.
- Compatible con una CSP estricta con nonce: sin `<script>` en línea, sin atributos de evento, sin `style` con datos dinámicos.

## Estructura

| Ruta | Función |
| --- | --- |
| `app/Http/Controllers/SitioPrototipoController.php` | Carga datos (con `RestauranteScope` liberado en `finally`), 404 con `menu.no-disponible`, mismas cabeceras de seguridad que el menú público |
| `resources/views/sitio/prototipo.blade.php` | Estructura: cabecera, portada, historia, galería, pie |
| `resources/views/sitio/partials/` | `menu`, `reservas`, `contacto` |
| `resources/views/components/sitio/icono.blade.php` | Íconos SVG propios |
| `resources/css/sitio.css` | Tema Tailwind v4, tipografías autoalojadas, foco, animación |
| `resources/js/sitio.js` | Aparición de secciones, menú móvil y formulario de reservación |
| `public/fonts/sitio/` | Fraunces e Inter (woff2, licencia OFL incluida) |
| `public/images/sitio-demo/` | Fotos WebP y `CREDITOS.md` |
| `tests/Feature/SitioPrototipoTest.php` | Pruebas del prototipo |

## Reservaciones

No hay tabla ni endpoint. El formulario valida en el navegador (nombre, teléfono de 8 a 15 dígitos, fecha entre hoy y
90 días, hora dentro del horario real incluyendo turnos que cruzan la medianoche, 1 a 12 personas, notas hasta 300
caracteres), compone un mensaje y abre `https://wa.me/<número>?text=<mensaje codificado>`. El número, el nombre del
restaurante y los turnos se leen de atributos `data-` del formulario. Si el restaurante no tiene WhatsApp, se muestra
su teléfono en lugar del formulario. Sin JavaScript, el formulario avisa y ofrece el teléfono.

## Contenido editable

El contenido propio del sitio ya no está escrito en las vistas: vive en dos tablas por restaurante y se edita desde el panel.

- `sitio_configuraciones` (una fila por restaurante): foto de portada y sus créditos, frase de portada, historia (etiqueta, título,
  dos párrafos, foto y créditos), hasta 3 datos destacados (JSON) y el interruptor de reservaciones.
- `sitio_galeria` (hasta 12 fotos por restaurante): imagen, título, texto alternativo, créditos, orden y visibilidad.

Quién edita:

- Propietario: página "Sitio web" del panel (`/admin/{slug}/sitio-web`). El editor no la ve.
- Superadmin: la misma pantalla desde Restaurantes, acción "Sitio web" (`/admin/{slug}/restaurantes/{id}/sitio`), para cualquier restaurante.
- Permisos en `SitioConfiguracionPolicy` y `SitioGaleriaPolicy` (propietario y superadmin).

Imágenes: se suben con `ImagenRestauranteUpload`, se convierten a WebP con `WebpImageService` y se guardan solo en
`restaurantes/{id}/sitio/`. Los modelos rechazan cualquier otra ruta (otro restaurante, otra carpeta, traversal). El límite de
peso sale de `IMAGENES_MAX_BYTES` y el texto de ayuda lo refleja.

Degradación: sin portada hay un fondo de color; sin historia, datos ni foto la sección se oculta; sin galería se oculta;
con reservaciones desactivadas desaparecen el formulario y sus botones. Los créditos solo se muestran si existen. Cada guardado
queda en el historial de cambios y purga la caché del menú.

Datos reales de siempre (de la BD): nombre, descripción, categorías, platillos, precios, ofertas, variantes, etiquetas, agotados,
horarios, contactos, dirección y estado abierto/cerrado.

Demo: `DemoRestauranteSeeder` carga los textos de La Esquina y copia las fotos de `public/images/sitio-demo` por el mismo servicio WebP.
Los textos y pies de foto son de relleno (no describen a ningún restaurante real) y las fotos son CC BY: el crédito viaja como dato
(ver `CREDITOS.md`). Si el demo ya existía sin contenido de sitio, volver a correr el seeder solo lo añade; nunca pisa lo editado.

## Limitaciones y decisiones pendientes

- Un solo idioma: el `idioma_default` del restaurante. No hay traducciones del sitio.
- La ruta `/sitio/{slug}` sigue solo en local y testing. Publicarla en producción es una decisión pendiente (ver comentario en `routes/web.php`).
- Una sola plantilla visual; no hay plantillas por tipo de restaurante.
- La portada y la foto de la historia no tienen texto alternativo propio: se tratan como decorativas o con un texto genérico.

## Accesibilidad y rendimiento

Jerarquía de encabezados h1 > h2 > h3 > h4, enlace "Saltar al contenido", etiquetas asociadas a cada campo con
mensajes de error por `aria-describedby` y `role="alert"`, foco visible, objetivos táctiles de al menos 44 px,
contraste de texto de 4.5:1 o más, animaciones desactivadas con `prefers-reduced-motion`, imágenes con ancho y alto
y carga diferida (la portada con `fetchpriority="high"`), sin Google Fonts ni CDN, sin iframes ni mapas externos.

## Límites conocidos

- El procesado de imágenes limita el lado mayor a 1200 px; en pantallas muy grandes la portada puede verse algo suave.
- La ubicación es un bloque estático con enlace a OpenStreetMap, no un mapa interactivo.
- Las fotos son ilustrativas y no corresponden al restaurante demo.
