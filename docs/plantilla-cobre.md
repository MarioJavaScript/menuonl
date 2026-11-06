# Plantilla O · Negro y cobre

Sitio (`/{slug}`) y menú (`/{slug}/menu`) con diseño propio, a partir de los prototipos `Cobre-Sitio.dc.html` y `Cobre-Menu.dc.html`. Solo visual: los datos salen de `SitioPublicoDatos` y del menú; el pedido y la reserva son los reales de las plantillas anteriores.

## Parámetros

- Clave `o`, nombre "Negro y cobre", prefijo de clases `cob-`, fuentes Marcellus + Karla.
- Carrito: `menuonl_cart_cob_{slug}` con `iniciarPedido('cob', 'menuonl_cart_cob')`.
- Modelo: plantilla `m` (Hamburguesas). Las letras `a` y `c` están retiradas y no se reutilizan.
- Sin choques: la clave del carrito, el prefijo y la letra no existían en el código.

## Archivos

- Vistas: `resources/views/sitio/o.blade.php`, `resources/views/menu/plantillas/o.blade.php`.
- Componentes: `resources/views/components/sitio/cobre/` (`layout`, `cabecera`, `visita`, `reservar`, `pedido`, `pie`).
- Hoja: `resources/css/sitio/o.css`. Scripts: `sitio-cobre.js`, `sitio-cobre-pedido.js`, `sitio-cobre-pedido-mensaje.js`.
- Fuentes y licencias: `public/fonts/sitio/` (`marcellus-cobre-latin-400`, `karla-cobre-latin-400/500/700`, `OFL-marcellus.txt`, `OFL-karla.txt`).
- Pruebas: `SitioCobreTest`, `PedidoWhatsappCobreTest`, `ReservasCobreTest`, `tests/Js/pedido-cobre.test.mjs`, `tests/Js/pedido-mensaje-cobre.test.mjs`.

## Decisiones aplicadas

- Galería con `galeria_fotos`: alt = descripción escapada o "Foto de {nombre}"; `aspect-ratio: 4 / 3`, `object-fit: cover`, `auto-fill` con ancho máximo de 22 rem. `o` está en `PLANTILLAS_CON_GALERIA`.
- Pedido en diálogo con `<x-sitio.entrega>` (Domicilio / Para recoger), sin `innerHTML` ni `onclick`.
- Se omite lo que no hay: sin relleno, sin "Te confirmamos por WhatsApp", sin "cierra X", sin subtítulo bajo el nombre en la cabecera; Recomendados solo con destacados; horario con los 7 días; miniatura solo con foto; logo con tamaño máximo fijo (2.75 rem) o el nombre.
- El prototipo no trae cinta decorativa, así que no se dibuja ninguna. Los números de sección (01, 02...) son decorativos y se cuentan solo entre las secciones dibujadas.
- "Cerrado" siempre con la palabra y punto hueco. `?mesa=` se ignora sin insignia.
- El acento del restaurante solo es fondo de botones y marcas (el hover conserva el mismo color); el texto de precios, números y enlaces usa siempre `--cob-cobre-texto`.
- No hay `a:hover` global: cada superficie define el suyo.
- Botones del pie con alto fijo para que alineen aunque pasen a una segunda fila.

## Fuentes

Subconjunto latín + acentos del español (fonttools, sin hinting), woff2, `font-display: swap`, con preload de Marcellus.
Karla era variable (`wght` 200–800): se fijaron instancias estáticas de 400, 500 y 700 (sin tabla `fvar`, `usWeightClass` 400/500/700). Marcellus es estática de un solo peso: todo título lleva `font-weight: 400`.

| Archivo | Bytes |
|---|---|
| `marcellus-cobre-latin-400.woff2` | 13 672 |
| `karla-cobre-latin-400.woff2` | 10 216 |
| `karla-cobre-latin-500.woff2` | 10 376 |
| `karla-cobre-latin-700.woff2` | 10 420 |

Peso final: 44 684 B. Los cuatro sha256 son distintos entre sí y de los demás woff2.

## Contraste (variables reales de `o.css`)

Texto, 4.5:1 mínimo: crema/negro 16.3, crema/panel 15.3, suave/panel 8.9, mudo/panel 6.8, cobre claro/panel 8.4, abierto/panel 11.5, cerrado/panel 9.3, error/panel 9.5, negro sobre cobre 6.0, sobre cobre de hover 7.8, sobre cobre claro (hover del botón crema) 8.9, negro sobre crema 16.3.
Interfaz, 3:1 mínimo: borde de campos y controles/panel 5.1, cobre/panel 5.6, punto de abierto/panel 8.6.
Los hover se miden por prueba (texto contra fondo, o contra negro y panel si solo cambian el color).

## Fuera de la plantilla

`app/Services/Catalogos.php` (entrada `o`), `app/Services/AparienciaSitio.php` (ficha y `PLANTILLAS_CON_GALERIA`; no va en `PLANTILLAS_CON_ESTILO`), `vite.config.js`, `lang/es/panel.php` (texto del estilo), y la clave `o` sumada a las pruebas que listan claves.

## Pendiente de verificar

Navegador real (360 a 1360 px, diálogo del pedido, fotos reales), MariaDB y Node 22.
