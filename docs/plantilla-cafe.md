# Plantilla P "Cafetería fresca"

Resumen de la plantilla `p`. Prototipo: `plantilla-cafeteria/Cafe-Sitio.dc.html` y `Cafe-Menu.dc.html`. Solo visual: datos de ejemplo del prototipo sustituidos por los reales del restaurante.

> Nota de nombres: la plantilla `e` ya se llama "Cafetería" y ocupa los archivos `cafeteria` (y `docs/plantilla-cafeteria.md`). Para no pisarla, esta plantilla usa el identificador interno `cafe` en archivos y carpetas, y este documento se llama `plantilla-cafe.md`. El nombre visible es "Cafetería fresca" para no repetir el de `e` ("Cafetería"); una prueba verifica que ningún nombre del catálogo se repita.

## Parámetros

- Clave `p`; prefijo de clases `caf-`; carrito `menuonl_cart_caf_{slug}` con `iniciarPedido('caf', 'menuonl_cart_caf')`.
- Fuentes: Young Serif (títulos, precios, nombre) y DM Sans (texto).
- Vistas: `sitio/p`, `menu/plantillas/p`, componentes en `components/sitio/cafe/`.
- Hoja `resources/css/sitio/p.css`; scripts `sitio-cafe.js`, `sitio-cafe-pedido.js`, `sitio-cafe-pedido-mensaje.js`.

## Decisiones

- Sin eslogan: bajo el nombre solo va la descripción, si existe. Sin cinta decorativa (el prototipo no la trae). Sin "cierra X", sin "te confirmamos por WhatsApp", sin textos de relleno.
- "Los favoritos" solo con destacados visibles; la foto solo si el platillo la tiene. Galería con `galeria_fotos` (alt escapado o "Foto de {nombre}"), 4 / 3 con recorte, rejilla `auto-fill` de máximo 22 rem; `p` está en `PLANTILLAS_CON_GALERIA`.
- Horario con los 7 días y hoy marcado. Estado "Abierto"/"Cerrado" con palabra y punto (hueco al cerrar).
- Reserva: formulario real con franjas libres, nota y aviso de privacidad (`sitio.js`).
- Pedido en diálogo con `x-sitio.entrega`; `?mesa=` se ignora. Botones del pie con el mismo alto aunque pasen a otra fila.
- Acento del restaurante solo como fondo (botón de pedir, "+", categoría activa, bloque de reserva, barra de pedido) con el texto medido; los textos usan siempre el verde base.

## Fuentes (OFL, autoalojadas en `public/fonts/sitio`)

Subconjunto latín + español con fonttools 4.68, sin hinting, woff2. DM Sans era variable (`opsz`, `wght`): se fijaron instancias estáticas (opsz 14). Verificado: sin tabla `fvar` y `usWeightClass` correcto en cada archivo.

| Archivo | Peso | Bytes |
|---|---|---|
| `young-serif-cafe-latin-400.woff2` | 400 | 14 548 |
| `dm-sans-cafe-latin-400.woff2` | 400 | 11 600 |
| `dm-sans-cafe-latin-500.woff2` | 500 | 11 796 |
| `dm-sans-cafe-latin-700.woff2` | 700 | 11 712 |

Total: 49 656 B. Licencias: `OFL-young-serif.txt`, `OFL-dm-sans-cafe.txt`. Young Serif solo tiene peso 400: todo título lleva `font-weight: 400`.

## Contraste (medido sobre las variables de la hoja)

Texto 4.5:1: café sobre blanco 15.7 y crema 13.0; tinta 8.0 / 6.7; verde sobre blanco 5.9 y crema 4.9; blanco sobre verde 5.9 (hover 7.9). Controles 3:1: borde `#85766a` 4.4 sobre blanco y 3.6 sobre crema; punto "Abierto" 4.1; los campos del bloque de reserva usan como borde el color del texto del bloque.

## Pruebas

`SitioCafeTest`, `PedidoWhatsappCafeTest`, `ReservasCafeTest` y los `.mjs` `pedido-cafe` y `pedido-mensaje-cafe` (entran por `PedidoWhatsappJsTest`). La clave `p` se sumó a las pruebas existentes que listan claves.
