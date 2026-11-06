# Plantilla N "Mexicana colorida"

Prototipos: `C:\Users\thexl\Desktop\proyectos\plantilla-mexicana\Mexicana-Sitio.dc.html` y `Mexicana-Menu.dc.html` (solo referencia visual). Modelo técnico: plantilla M (Hamburguesas), sobre L, K, J e I.

## Parámetros

- Clave `n` (`sitio_plantilla` es `string(1)`, sin migración). Nombre visible **Mexicana colorida**. Prefijo de clases `mex-`, variables `--mex-*`. Vistas `sitio.n` y `menu.plantillas.n`; componentes en `components/sitio/mexicana/`.
- Carrito: `menuonl_cart_mex_{slug}` con `iniciarPedido('mex', 'menuonl_cart_mex')`. Se verificó con grep que ni el prefijo ni la clave existían.
- Fuentes autoalojadas (OFL) en `public/fonts/sitio`, subconjunto latín con acentos del español, sin hinting, nombre propio:
  - `alfa-slab-one-mexicana-latin-400.woff2` (8 960 B, estática, peso 400)
  - `work-sans-mexicana-latin-400.woff2` (14 068 B), `-600` (14 660 B), `-700` (14 556 B)
  - Work Sans era variable (`wght`): se fijaron instancias estáticas con `fontTools.varLib.instancer`. Se verificó sin tabla `fvar` y con `usWeightClass` 400 / 400 / 600 / 700.
  - Peso final: 52 244 B. Los cuatro sha256 son distintos entre sí y de los demás woff2.
  - Licencias: `OFL-alfa-slab-one.txt`, `OFL-work-sans.txt`.
  - Alfa Slab One solo tiene peso 400: todo título y toda regla con la fuente de títulos lleva `font-weight: 400`.

## Decisiones aplicadas

- Portada: nombre (h1), eslogan, abierto/cerrado ahora, horario de hoy y botones; con portada, foto en arco con borde dorado (4 / 5). Montañas decorativas estáticas (SVG `aria-hidden`, sin datos ni animación).
- "Nuestra historia" muestra la descripción del restaurante y se omite sin ella. No hay cinta de categorías: el prototipo no la trae.
- Recomendados solo con destacados visibles. Galería con `galeria_fotos` (alt = descripción escapada o "Foto de {nombre}"), `aspect-ratio: 4 / 3`, `object-fit: cover`, `auto-fill` con 22 rem máximo por tarjeta. `n` está en `PLANTILLAS_CON_GALERIA`.
- Horario con los 7 días; hoy marcado. Botones del pie (Cómo llegar, WhatsApp, Llamar, Instagram, Facebook) con el mismo alto aunque caigan a otra fila.
- Reserva: formulario real (franjas libres, nota, aviso de privacidad). Sin "te confirmamos por WhatsApp".
- Pedido: script propio (`sitio-mexicana-pedido.js`, sin `innerHTML` ni `onclick`), `x-sitio.entrega`, pedido en `<dialog>`, variantes y notas. Miniatura en el menú solo si el platillo tiene foto. `?mesa=` se ignora sin insignia.
- Ficha en `AparienciaSitio::fichas()`; no está en `PLANTILLAS_CON_ESTILO`. Texto de `lang/es/panel.php` actualizado.

## Contraste (medido sobre las variables reales de la hoja, con prueba)

Texto (≥ 4.5:1): crema/café 13.96, arena/café 12.09, café/dorado 9.00, blanco/terracota 5.08 (hover 7.31), terracota de texto/crema 4.94 y /blanco 5.92, tinta/blanco 9.48, crema/píldora "Cerrado" 10.38, blanco/verde 7.64, menta/verde 6.18, error/blanco 9.32, placeholder/blanco 5.38.
Interfaz (≥ 3:1): borde de campos/blanco 6.23 y /crema 5.19, café/blanco 16.7, abierto/verde 5.08, dorado/café 9.00 y dorado/verde 4.11 (anillo de foco).
Reglas verificadas: la terracota base (4.24 sobre crema, 3.30 sobre café) nunca es texto, se usa la variante de texto `#b13d15`; el dorado nunca es texto sobre crema, blanco o verde; ningún hover deja el texto igual al fondo; "Cerrado" lleva la palabra, borde y punto hueco.

## Archivos

Nuevos: `resources/css/sitio/n.css`, `resources/js/sitio-mexicana{,-pedido,-pedido-mensaje}.js`, `resources/views/sitio/n.blade.php`, `resources/views/menu/plantillas/n.blade.php`, `resources/views/components/sitio/mexicana/{cabecera,layout,pedido,pie,reservar,visita}.blade.php`, fuentes y licencias, pruebas `SitioMexicanaTest`, `PedidoWhatsappMexicanaTest`, `ReservasMexicanaTest`, `tests/Js/pedido-mexicana.test.mjs`, `tests/Js/pedido-mensaje-mexicana.test.mjs`.
Modificados (solo registro de la clave): `AparienciaSitio.php`, `Catalogos.php`, `lang/es/panel.php`, `vite.config.js` y las pruebas existentes que listan claves.
