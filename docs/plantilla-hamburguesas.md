# Plantilla M "Hamburguesas" · Fase 1 (análisis, sin código)

Prototipos leídos: `C:\Users\thexl\Desktop\proyectos\hamburguesas\Burger-Sitio.dc.html` (escritorio 1280 px) y `Burger-Menu.dc.html` (celular 390 px). Solo referencia visual: estilos inline y datos de `renderVals()`; el runtime del lienzo (`support.js`, `<x-dc>`, `<sc-for>`, `data-props`) se ignora. Modelo técnico: plantilla L (Negro y esmeralda), sobre K, J e I.

Estado: solo lectura y este documento. No hay código, migración ni cambios en otras plantillas. Sin `git add`, commit ni rama. No se tocó `.env`, `public/hot`, `public/build`, backups ni el `.;.swp`.

## Clave y nombres

- Clave **`m`** (`sitio_plantilla` es `string(1)`, sin migración; `m` está libre en `Catalogos::plantillasSitio`, `AparienciaSitio` y vistas). Nombre visible **Hamburguesas**. Prefijo `ham-`, variables `--ham-*`. `a` y `c` siguen retiradas. Que `m` sea slug reservado de URL (`Catalogos` línea 23) no afecta: es otra cosa.
- Vistas: `sitio.m` y `menu.plantillas.m`; componentes en `components/sitio/hamburguesas/`. No entra en `RecomendadosDelSitio::PLANTILLAS_CON_PRINCIPAL`.

## DECISIÓN PENDIENTE (hallazgo que me detiene): la clave del carrito

Pediste `menuonl_cart_m_{slug}`, pero **esa clave ya está en uso** por el menú genérico (`menu.publico`, "m" = menú): `resources/views/menu/publico.blade.php:350` (`data-clave="menuonl_cart_m_{slug}"`), `resources/js/menu-pedido.js:18` (`iniciarPedido('mo', 'menuonl_cart_m')`) y `PedidoWhatsappBarTest.php:414`. Consecuencias si la reutilizo:

1. El carrito de la plantilla m y el del menú genérico compartirían `localStorage` para el mismo slug (mezcla al cambiar de plantilla).
2. La prueba "b, i, j, k y l no llevan `menuonl_cart_m`" **fallaría**: b usa `menu.publico` y siempre lleva esa clave.

Propuesta: **`menuonl_cart_ham_{slug}`** (`iniciarPedido('ham', 'menuonl_cart_ham')`). Las otras plantillas usan su letra porque no chocaban; aquí la letra ya está tomada. La prueba de aislamiento busca `menuonl_cart_ham_`, `ham-` (como clase, con límite de palabra) y `data-ham-`. No toca ninguna otra plantilla. **Confírmame esta clave antes de la fase 2** (si prefieres otra, dime cuál).

## Paleta y contraste del prototipo (fórmula WCAG, medido ahora)

Colores: negro `#141414`, crema `#F7F1E3`, mostaza `#E8B020`, rojo `#B8322A`, blanco, píldora `#2A2A2A`, texto suave `#D8D2C4`, texto de tarjeta `#4A443A`, marcador `#EADFC6`/`#DDD0B3`/`#3A3632`, verde "abierto" `#9BE3A8`/`#BDEBC8`.

| Par | Contraste | Veredicto |
|---|---|---|
| Crema sobre negro / `#D8D2C4` sobre negro | 16.36 / 12.23 | bien |
| Negro sobre crema, negro sobre mostaza (botones, cinta, chips, "+") | 16.36 / 9.35 | bien |
| Mostaza como texto sobre negro | 9.35 | bien (solo sobre negro) |
| Blanco sobre rojo `#B8322A` (botón Arma tu pedido, barra del pedido) | 5.95 | bien |
| Crema sobre rojo | 5.29 | bien |
| Rojo como texto sobre crema / blanco (precios, "Ver el menú completo") | 5.29 / 5.95 | bien |
| `#4A443A` sobre blanco / crema | 9.64 / 8.56 | bien |
| Crema sobre `#2A2A2A` (píldora) | 12.74 | bien |
| `#9BE3A8` sobre `#2A2A2A`; `#BDEBC8` sobre negro | 9.53 / 13.93 | bien |
| **Rojo como texto sobre negro** | **3.09** | **falla**: no usarlo como texto sobre negro |
| **Mostaza como texto sobre crema / blanco** | **1.75 / 1.97** | **falla**: mostaza solo como fondo con texto negro, o texto sobre negro |
| Rojo como texto sobre `#EADFC6` | 4.50 | en el límite exacto: no poner texto rojo sobre ese tono |
| **Hover global `a:hover{color:#B8322A}`** sobre el botón mostaza "Pedir ahora" / "Cómo llegar" (texto negro pasa a rojo) | **3.02** | **falla** |
| **Hover global** sobre enlaces de la cabecera y del pie (fondo negro) | **3.09** | **falla** |
| **Hover global sobre el botón "Arma tu pedido" (fondo `{{accent}}` = rojo)**: el texto pasa a rojo sobre rojo | **1.00** | **falla grave: el texto desaparece** |
| Hover global sobre "Reservar" (contorno crema) | 3.09 | falla |
| **"+" mostaza contra tarjeta blanca** (sin borde) | **1.97** | **falla** como componente de interfaz (pide 3:1) |
| Texto negro de la reserva sobre el bloque mostaza | 9.35 | bien |
| Borde de campos `#141414` sobre blanco | 18.42 | bien |
| Opción de entrega elegida: crema sobre negro; sin elegir: borde negro sobre blanco | 16.36 / 18.42 | bien |
| Píldora `#2A2A2A` contra negro | 1.28 | decorativa; el estado va en texto |
| Estado **Cerrado** | no definido en el prototipo | ver correcciones |

### Correcciones propuestas (se confirman midiendo las variables reales de `m.css`, con prueba)

- **Reglas de uso**: mostaza = fondo (texto negro) o texto sobre negro; rojo = fondo (texto blanco o crema) o texto sobre crema/blanco, nunca sobre negro ni sobre `#EADFC6`.
- **Hover por superficie, sin regla global**:
  - Enlace o texto sobre negro: crema pasa a mostaza `#E8B020` (9.35) con subrayado.
  - Enlace de texto sobre crema ("Ver el menú completo", rojo 5.29): hover a negro (16.36) con subrayado.
  - Botón mostaza (texto negro): hover a mostaza clara `#F2C94C` con texto negro (11.61), subrayado; el texto no cambia de color.
  - Botón rojo (texto blanco): hover a rojo oscuro `#962820`, blanco sobre él 8.01; el texto no cambia. Con acento propio del restaurante, `--ham-acento-hover` lo inyecta la vista (igual que en L) y vale el mismo acento con subrayado y trazo, para que ningún acento deje el texto igual al fondo.
  - Botón con contorno crema sobre negro: hover relleno crema con texto negro (16.36).
  - Botón negro de la reserva (crema sobre negro): hover a fondo crema con texto negro y borde negro.
- **"+" del menú**: mostaza con borde de 2 px negro (negro contra blanco 18.42, contra mostaza 9.35). Vale como componente de interfaz.
- **Foco visible**: anillo negro de 3 px con separación sobre superficies claras (16.36 sobre crema) y mostaza sobre negro (9.35); sobre el bloque mostaza, negro.
- **Estado Cerrado**: `#F0A6A0` sobre negro 9.36, siempre con la palabra y un punto hueco (abierto: punto sólido `#9BE3A8`). Error de la reserva: sobre el bloque mostaza, `#7A1710` = 5.45 (más oscuro que `#8B1A14`, 4.73).
- **Acento del restaurante** (`color_acento`): como en I a L, `colorAcentoSeguro()`/`colorAcentoTexto()`: solo como fondo de botones y marcas con texto de contraste medido; sin acento propio, rojo `#B8322A`. Las opciones del `data-props` (rojo, negro, verde) eran del editor del lienzo.

## Datos: de dónde sale cada bloque

Leyenda: **hay** = campo existente; **derivado** = se calcula; **omito**; **falta** = no existe y no se inventa. Sin migración ni cambio de datos.

### Sitio (`/{slug}`)

| Bloque del prototipo | Campo de Menuonl | Estado y lo que se construye |
|---|---|---|
| Logo: cuadro mostaza con "N" | `logo`, `nombre` | Cambio. Logo real con tamaño máximo fijo (`object-fit: contain`); sin logo, solo el nombre. Sin letra inventada |
| Nombre "Norteño Burger Club" (Anton 26 px, `nowrap` + puntos suspensivos) | `nombre` | hay, pero sin `nowrap`/elipsis: el nombre largo se parte en líneas (`overflow-wrap: anywhere`). Truncar escondería el nombre |
| Navegación: Menú, Galería, Horario, Pedir ahora | `galeria_fotos`, `horarios`, `direccion`, `mapa_url`, `pedidosHabilitados()` | "Menú" siempre; "Galería" solo con fotos; "Horario" con algún día abierto, dirección o mapa; "Pedir ahora" solo con pedidos habilitados. Se agrega "Reservar" con reservas activas. En 360 px la navegación baja a una segunda fila y se recorta/envuelve sin desborde |
| Píldora "Abierto ahora · cierra 12:00 a. m." | `esta_abierto`, `horarios` | derivado. "Abierto ahora" / "Cerrado ahora" (palabra + punto distinto), solo si hay algún día abierto. **Sin "cierra X"** |
| H1 "Smash al carbón" (Anton 104 px) | `nombre` | **omito la frase**: es de venta y Menuonl no la conoce. El h1 es el nombre en Anton, mayúsculas, con `clamp()` y `overflow-wrap` |
| Párrafo "Carne de res molida en casa…" | `eslogan`, `descripcion` | derivado; cada uno se omite si está vacío. **Sin subtítulo bajo el nombre** |
| (no está) "Hoy: horario" | `horarios`, zona horaria | se agrega como en I a L; se omite si hoy está cerrado |
| Botón "Arma tu pedido" | `menu_url`, `pedidosHabilitados()` | "Arma tu pedido" con pedidos habilitados; si no, "Ver el menú" |
| Botón "Reservar" | `reservas_activas` | Solo con reservas activas |
| Portada cuadrada 1:1 con bloque mostaza desplazado, marcador "Foto de portada" | `portada` | Foto real recortada 1:1 (máx. 480 px de alto), con el bloque mostaza decorativo (`aria-hidden`). Sin portada, se omite todo el bloque (sin marcador) y el texto ocupa el ancho. Alt "Foto de {nombre}" |
| Cinta mostaza "Smash doble · Papas a mano · Malteadas" | `categorias` | **omito el texto fijo** (afirma un menú ajeno). Propuesta (decisión 5): cinta estática, sin animación, con los nombres reales de las categorías visibles, `aria-hidden`; solo con 2 o más categorías. Si dices que no, se omite la cinta |
| "Las favoritas" + 4 tarjetas (foto 4:3, nombre, precio rojo, descripción) | `destacados` (hasta 6, sin agotados), `imagen` | Mismo diseño. Foto del platillo solo si la tiene; sin foto, la tarjeta es solo texto, sin recuadro vacío. Precio vigente, anterior tachado, etiquetas. Rótulo "Recomendados" (**solo si hay destacados**); la sección se omite si no hay |
| "Ver el menú completo" | `menu_url` | hay |
| Galería: 4 cuadros 1:1 de color | `galeria_fotos` (`SitioPublicoDatos`) | Cambio. Fotos reales en su orden, **`aspect-ratio: 4 / 3` y `object-fit: cover`**, rejilla `auto-fill` con ancho máximo por tarjeta (como `.cor-galeria` y L). Alt = descripción escapada; vacía: "Foto de {nombre}". Se omite sin fotos |
| Horario: 3 filas agrupadas | `horarios` | Cambio. Los 7 días, lunes primero, sin agrupar; hoy marcado; día cerrado dice "Cerrado" |
| Visítanos: dirección en dos líneas | `direccion` | hay; se omite si falta. Sin dirección el título pasa a "Contacto" |
| Botones Cómo llegar, WhatsApp, Llamar | `mapa_url`, `whatsapp_digitos`, `telefono_enlace`, Instagram, Facebook | Cada uno si tiene dato. **Mismo alto y alineación aunque caigan a una segunda fila** (`min-height` común, borde de 2 px en todos, `box-sizing`). Sin horario ni contacto la sección no se dibuja |
| Reserva: fecha, hora (`type="time"`), personas, nombre, teléfono, "Enviar reserva" | Formulario real (`sitio.js`, `data-url`, `data-disp`) | Cambio. Hora = franjas libres; se suman nota opcional, campo trampa, aviso de privacidad y mensaje de estado. Botón "Solicitar reserva" |
| "Te confirmamos por WhatsApp." | n/a | **omito** |
| Pie: nombre + "Hecho con Menubly" | `nombre`, `config('app.name')` | Nombre, enlaces internos y "Sitio creado con {app}" |

### Menú (`/{slug}/menu`)

| Bloque del prototipo | Campo de Menuonl | Estado y lo que se construye |
|---|---|---|
| Cabecera: logo "N" + nombre | `logo`, `nombre` | Logo real con tamaño máximo fijo (alto 40 px) o solo el nombre; el nombre se parte en líneas, no se trunca |
| "Abierto" con punto | `$estaAbierto` | "Abierto" / "Cerrado" siempre con palabra |
| H1 "Menú" (Anton 52 px) | texto fijo | "Menú". Nunca "Carta" |
| Selector de idioma (no está) | `idiomasDisponibles` | solo con más de un idioma |
| Franja mostaza de categorías (Burgers, Papas, Malteadas, Bebidas) | `categorias` visibles con platillos | Las del restaurante, no las del prototipo. Anclas `#categoria-N`, `aria-current` en la actual (chip negro con texto crema). Solo con más de una categoría |
| Título de categoría (Anton 30 px) y tarjetas con borde de 2 px | traducciones, `precioVigente()`, `FormatoMoneda` | Mismo diseño. Precio anterior tachado y etiquetas |
| Miniatura 76 px | `imagen` | **El prototipo siempre la pinta.** Aquí solo si el platillo tiene foto; sin foto no hay recuadro |
| Variantes | `producto_variantes` | **falta en el prototipo**: cada variante en su línea con su precio y su "+" (nombre accesible "Agregar {platillo} · {variante}") |
| Agotado | `disponible`, `mostrar_agotados` | **falta**: etiqueta "Agotado" con texto, sin botón |
| Botón "+" 44 px | datos del servidor | Nombre accesible "Agregar {platillo} al pedido"; borde negro de 2 px (ver contraste); sin JavaScript queda oculto y se ofrece el enlace de WhatsApp |
| Tarjeta "Tu pedido" en la página (¿Cómo lo quieres?, líneas, notas, total) | controlador de pedido compartido | Cambio. Pasa a `<dialog>` con líneas, **nota por línea de 80 caracteres** ("sin cebolla", "extra queso"), `<x-sitio.entrega>` (Domicilio / Para recoger), "Notas para la cocina" y total. El ejemplo "Una sin pepinillos" no se lleva |
| Barra inferior `absolute` "Enviar por WhatsApp · 3 · $395" | estado en `localStorage` | Barra `fixed` "Ver pedido" con cuenta y total; abre el diálogo y el envío va dentro. El `absolute` era del lienzo |
| Aviso de cerrado | `pedidosHabilitados()`, `$estaAbierto` | **falta**: "Estamos cerrados ahora…" y sin "+" |
| Horario, contacto y pie | `SitioPublicoDatos` | **falta**; van al final como en I a L |
| Ancho 390 px y alto 2300 px | n/a | Cambio: fluido, contenedor centrado (máx. ~720 px), alto automático |
| `?mesa=` | n/a | Se ignora por completo (sin insignia ni formulario) |

## Textos de la portada

Todo sale de campos existentes, sin migración:

- h1 = `nombre`; debajo `eslogan` y `descripcion`, cada uno omitido si está vacío. **Sin subtítulo bajo el nombre.**
- Píldora y "Hoy: …" = `horarios` + zona horaria.
- Botones según `pedidosHabilitados()`, `reservas_activas` y contacto.
- Rótulos fijos que no afirman nada: "Recomendados", "Ver el menú completo", "Galería", "Horario", "Visítanos", "Contacto", "Aparta tu mesa" (rótulo de la reserva), "Solicitar reserva", "Tu pedido", "Menú".
- Sin "Smash al carbón", testimonios, "te confirmamos por WhatsApp" ni "cierra X".

## Cambios respecto al prototipo, y por qué

1. Logo real o nombre; no la "N" inventada. El nombre se parte, no se trunca.
2. H1 = nombre, sin la frase de venta (no existe el dato).
3. Cinta con categorías reales o sin cinta, no con platillos inventados.
4. Recomendados solo con destacados; foto de tarjeta solo si el platillo la tiene.
5. Galería con fotos reales 4:3 (el prototipo usaba cuadros 1:1 de color) y rejilla con ancho máximo.
6. Horario de 7 días sin agrupar.
7. Reserva real; sin "Te confirmamos por WhatsApp".
8. Pedido en diálogo con barra fija; variantes, notas, agotados, aviso de cerrado, horario y contacto que el prototipo no tiene.
9. Contraste: hover global (deja el botón rojo ilegible y los demás bajo 4.5), "+" sin borde, Cerrado y reglas de uso del rojo y la mostaza.
10. Responsive: el h1 de 104 px y el nombre `nowrap` no caben a 360 px; columnas con `min(100%, …)`; navegación que envuelve; menú fluido.
11. Marcadores de foto omitidos; no se copian fotos.
12. Acento del restaurante, no del editor del lienzo.
13. `?mesa=` ignorado.
14. Botones del pie con alto y alineación fijos en cualquier fila.
15. Sin Google Fonts (el prototipo los carga de `fonts.googleapis.com`): fuentes autoalojadas.

## Títulos largos en Anton a 360 px

Anton es condensada y va en mayúsculas, pero un nombre como "Hamburguesas Don Chuy del Norte" no cabe en una línea. Reglas: h1 con `font-size: clamp(2.25rem, 9vw + 0.5rem, 6.5rem)` (≈ 41 px a 360 px; una palabra de 14 letras mide ≈ 6.3 em ≈ 255 px, cabe en 312 px), `line-height` 0.95, `overflow-wrap: anywhere` y `min-width: 0` en cada hijo flex. **Anton solo existe en peso 400**: todos los `h1`–`h3` y botones en Anton llevan `font-weight: 400` explícito para que el navegador no fabrique negrita falsa. Una prueba lee `m.css` y exige `overflow-wrap`, `clamp(` en el h1 y `font-weight: 400` en los selectores de Anton. Se verificará en navegador a 360 px con un nombre de 40 caracteres.

## Pedido por WhatsApp: opción A (confirmada)

Copia y adaptación de `sitio-esmeralda-pedido*.js`, sin tocar `pedido/controlador.js` ni `pedido/mensaje.js`:

- `resources/js/sitio-hamburguesas-pedido.js`: `iniciarPedido('ham', 'menuonl_cart_ham')` (ver decisión pendiente de la clave); la vista escribe `data-clave`.
- `resources/js/sitio-hamburguesas-pedido-mensaje.js`: `export * from './pedido/mensaje.js'`.
- `resources/js/sitio-hamburguesas.js`: categoría a la vista (`aria-current`) y `prefers-reduced-motion`.
- Sin `innerHTML` ni `onclick`; líneas desde `<template>`; `localStorage` solo con ids, cantidades y notas.
- `<x-sitio.entrega prefijo="ham" clase-campo="ham-campo" clase-etiqueta="ham-campo__etiqueta" />`, sin tocar el componente.
- Variantes y notas: ya las soporta el controlador compartido (id de variante en la línea, nota por línea de 80 caracteres). En la fase 3 las pruebas cubren "sin cebolla", "extra queso", comillas, `&`, `<script>`, saltos de línea y emoji hostil en nombres y notas, y que el enlace `wa.me/…?text=` salga con `encodeURIComponent` sin romper el texto.
- Respeta `pedidos_activos`, `whatsapp_pedidos` (8 a 20 dígitos) y "abierto ahora".

## Fuentes

Autoalojadas en `public/fonts/sitio/`, ambas OFL, subconjunto latín + acentos del español con `fonttools` 4.68 (mismo conjunto de I a L, sin hinting, woff2). Los TTF vienen de `google/fonts` (`ofl/anton/Anton-Regular.ttf`, `ofl/archivo/Archivo[wdth,wght].ttf`) y cada `OFL.txt` se copia sin cambios. Se descargan en la fase 2; no hay copia local de estos TTF.

**Anton**: estática, un solo peso (400). Sin fijar instancias.

**Archivo**: se espera variable con **ejes de ancho (`wdth` 62–125) y peso (`wght` 100–900)**. Se confirma con `fvar` al descargar. Se fijan instancias estáticas con `fontTools.varLib.instancer`, **ancho normal (`wdth = 100`)** y un peso por archivo; así no quedan ejes ni `fvar`.

**Pesos que usa el prototipo.** Anton: 400 (h1, h2, logo, precios, cinta). Archivo: 400 (campos, descripciones, `dd`), 700 (navegación, píldora, `dt`, opción sin elegir), 800 (nombres de platillo, botones, etiquetas, total). El prototipo carga además 500 y 600, que no se usan (el 600 solo aparece en los marcadores de foto, omitidos).

| Archivo previsto | Contenido | Nota |
|---|---|---|
| `anton-hamburguesas-latin-400.woff2` | Anton 400 | títulos, logo, precios |
| `archivo-hamburguesas-latin-400.woff2` | Archivo 400, wdth 100 | cuerpo y campos |
| `archivo-hamburguesas-latin-700.woff2` | Archivo 700, wdth 100 | navegación, píldora |
| `archivo-hamburguesas-latin-800.woff2` | Archivo 800, wdth 100 | nombres, botones |
| `OFL-anton.txt`, `OFL-archivo.txt` | licencias sin cambios | |

Peso estimado: Anton ~13 KB y Archivo ~7 KB cada instancia, unos 35 KB en total. **Peso final, `sha256sum` de cada woff2 y comprobación de pesos reales (sin tabla `fvar`, `usWeightClass` 400/700/800 en Archivo y 400 en Anton) los reporto en la fase 2**; los cuatro sha256 deben ser distintos entre sí y de todos los woff2 existentes. Nombre propio en `font-family` ("Anton Hamburguesas", "Archivo Hamburguesas") sin depender de fuentes de otras plantillas. Si prefieres dos pesos de Archivo (800 absorbido en 700), son 3 archivos en total; por defecto dejo tres. `font-display: swap`; `preload` solo de Anton.

## Qué se toca fuera de la plantilla (a registrar; lo reporto)

1. `app/Services/Catalogos.php`: `m` en `plantillasSitio()`.
2. `app/Services/AparienciaSitio.php`: ficha `m` en `fichas()` y `m` en `PLANTILLAS_CON_GALERIA` (`['b','i','j','k','l','m']`). **No** va en `PLANTILLAS_CON_ESTILO`.
3. `vite.config.js`: `m.css` y los scripts de entrada.
4. `lang/es/panel.php`: `estilo.descripcion` suma "Hamburguesas" a la lista; el aviso de Fotos se arma desde `PLANTILLAS_CON_GALERIA`; ajusto `PanelFotosDelRestauranteTest`.
5. Pruebas existentes que solo suman `m` a listas de claves: todos los `Sitio*Test`, `PanelAparienciaTest`, `TextosPrePilotoTest`, `PlanSitioPublicoTest`, `PanelFotosDelRestauranteTest`; los `.mjs` nuevos entran por `PedidoWhatsappJsTest`.

Sin cambios: `SitioPublicoDatos`, `MenuPublicoController`, `Restaurante`, `sitio.js`, `menu.js`, `menuonl.css`, `<x-sitio.entrega>`, `pedido/controlador.js`, `pedido/mensaje.js`, `.env`, `public/hot`, `public/build`, backups y las plantillas b a l.

Nota para el cierre: Pint puede cambiar los finales de línea de `AparienciaSitio.php`, `Catalogos.php` y `lang/es/panel.php`; se restauran al original.

### Ficha propuesta para `fichas()['m']`

- nombre: "Hamburguesas".
- descripción: "Fondo casi negro con amarillo mostaza y rojo, títulos condensados en mayúsculas. Menú en tarjetas."
- para: "Hamburgueserías, papas y malteadas".
- colores leídos de la hoja: fondo `#141414`, texto `#f7f1e3`, acento `#b8322a`, bloque `#e8b020`.

## Archivos que se crearán en fase 2

- Vistas: `resources/views/sitio/m.blade.php`, `resources/views/menu/plantillas/m.blade.php`, `resources/views/components/sitio/hamburguesas/{layout,cabecera,visita,reservar,pedido,pie}.blade.php`.
- Hoja `resources/css/sitio/m.css`; scripts `sitio-hamburguesas.js`, `sitio-hamburguesas-pedido.js`, `sitio-hamburguesas-pedido-mensaje.js`.
- Fuentes y licencias en `public/fonts/sitio/`.
- Pruebas: `SitioHamburguesasTest`, `PedidoWhatsappHamburguesasTest`, `ReservasHamburguesasTest`, `tests/Js/pedido-hamburguesas.test.mjs`, `tests/Js/pedido-mensaje-hamburguesas.test.mjs`.

## Plan de pruebas

- Toda prueba que dependa de abierto/cerrado, "Hoy:" o "Cerrado ahora" fija la hora con `travelTo`.
- Valores que caben en su columna (`color_acento` 7 caracteres; `whatsapp_pedidos` 8 a 20 dígitos; lo largo en memoria), `$modelo->id`, sin ids fijos. `withoutVite()` en el `setUp` si se lee `public/build`.
- `SitioHamburguesasTest`: catálogo, ficha, elegible en el panel, sitio y menú completos, abierto/cerrado con palabra, galería (orden, alt escapado, "Foto de {nombre}", sin fotos), `?mesa=` ignorado sin insignia, secciones omitidas (sin destacados, sin portada, sin logo), miniatura solo con foto, `color_acento` hostil, escape, sin recursos externos ni emojis, enlaces con `route()`, "Carta" ausente, prefijo `ham-` sin choque con `menuonl.css`, colores solo en variables, fuentes (existen, OFL, sha256 distintos, sin `fvar`, `usWeightClass`), títulos largos (`overflow-wrap`, `clamp`, `font-weight: 400` en Anton).
- Contraste medido sobre las variables reales de `m.css`: texto 4.5:1; bordes de campo y "+" 3:1; blanco sobre rojo y rojo oscuro de hover; negro sobre mostaza y mostaza clara de hover; ningún hover deja el texto igual al fondo; rojo nunca texto sobre negro, mostaza nunca texto sobre crema; Cerrado con palabra.
- **Regla de proporción de la galería**: la prueba lee `m.css` y exige `aspect-ratio: 4 / 3`, `object-fit: cover` y `auto-fill` con ancho máximo en `.ham-galeria`.
- **b, i, j, k y l no llevan marcas de m**: render de sitio y menú sin `ham-` (con límite de palabra), `data-ham-` ni `menuonl_cart_ham_`, y siguen en sus vistas. Sin huellas SHA de archivos.
- Pedido: variantes y notas, nombres y notas hostiles, enlace de WhatsApp escapado, respeto de `pedidos_activos` y de cerrado.
- Suma de `m` a las pruebas existentes (arriba); `.mjs` por `PedidoWhatsappJsTest` (Node 22, sin carpetas, sin APIs de Node 24).
- Pint con `--dirty`, restaurando finales de línea; después la suite.

## Riesgos

- Node local es más nuevo que el 22 del CI; el comportamiento en 22 solo lo confirma el CI.
- Navegador real a 360/390/768/1024/1360 px, diálogo del pedido, fotos reales y MariaDB: se verifican al terminar la fase 2 o en el CI. Si no hay Chrome para Playwright en este equipo, lo reporto como no verificado.
- Las fuentes se descargan de `google/fonts`; si no hay red, la fase 2 se detiene en ese punto y te lo digo.

## ¿Algo exige migración, cambiar datos o tocar otra plantilla?

Migración: no. Datos: no. Otra plantilla: no. **Sí me detengo en una cosa**: la clave del carrito (`menuonl_cart_m_` ya es del menú genérico). No exige tocar otra plantilla si usamos `menuonl_cart_ham_`; sí lo exigiría usar `menuonl_cart_m_` (habría que cambiar el genérico y su prueba).

## Decisiones tuyas (si no dices nada, aplico lo primero)

1. **Clave del carrito `menuonl_cart_ham_{slug}`** (necesito tu confirmación; ver arriba).
2. **Archivo 400, 700 y 800 estáticos con `wdth = 100`** más Anton 400 (cuatro woff2). Alternativa: 400 y 700 (tres).
3. **Hover**: botón rojo a `#962820`, botón mostaza a `#F2C94C`, enlaces sobre negro a mostaza, sin regla global.
4. **"+" con borde negro de 2 px** sobre mostaza.
5. **Cinta** con nombres reales de categorías (estática, `aria-hidden`, 2 o más categorías) o sin cinta.
6. **Miniatura de platillo** solo si hay foto, de 76 px (como el prototipo).

## Resultado de las fases 2 y 3

- Construido: vistas `sitio/m` y `menu/plantillas/m`, seis componentes en `components/sitio/hamburguesas/`, hoja `m.css`, tres scripts (`sitio-hamburguesas*.js`; pedido opción A con `menuonl_cart_ham_{slug}`), fuentes y licencias, registro en `Catalogos`, ficha y galería en `AparienciaSitio`, `vite.config.js` y `lang/es/panel.php`.
- Fuentes (subconjunto latín + español, sin hinting; sin tabla `fvar`; `usWeightClass` verificado en cada instancia): `anton-hamburguesas-latin-400.woff2` 9 188 B (400), `archivo-hamburguesas-latin-400.woff2` 11 716 B (400), `archivo-hamburguesas-latin-700.woff2` 11 660 B (700), `archivo-hamburguesas-latin-800.woff2` 11 668 B (800). Peso final: 44 232 B. Archivo era variable (`wght` 100–900, `wdth` 62–125): se fijó `wdth = 100`. sha256 distintos entre sí y de todos los woff2 existentes.
- Decisiones aplicadas: clave `menuonl_cart_ham_{slug}`, cinta estática con categorías reales (se omite sin categorías), hovers `#962820` / `#f2c94c` / mostaza sobre negro, "+" con borde negro de 2 px, Cerrado `#f0a6a0` con palabra y punto hueco.
- Contrastes medidos por prueba sobre las variables reales de `m.css` (texto 4.5:1, bordes 3:1, hovers en cuatro superficies); el rojo nunca es texto sobre negro y la mostaza nunca sobre crema o blanco.
- Verificación visual en Chrome (HTML renderizado con la hoja real, sin JavaScript) a 360, 390, 768, 1024 y 1360 px, con y sin fotos y con un nombre de 90 caracteres: sin desborde horizontal. No verificado: diálogo del pedido en navegador (necesita el build de Vite), fotos reales, MariaDB.
