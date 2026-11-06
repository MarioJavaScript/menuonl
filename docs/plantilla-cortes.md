# Plantilla K "Cortes premium" · Fase 1 (análisis, sin código)

Prototipos leídos: `C:\Users\thexl\Desktop\proyectos\cortes\Cortes-Sitio.dc.html` (escritorio 1280 px) y `Cortes-Menu.dc.html` (celular 390 px). Solo referencia visual: estilos inline y datos de `renderVals()`. El runtime del lienzo (`support.js`, `<x-dc>`, `<sc-for>`, `data-props`) se ignora. Modelo técnico: plantilla J (Mariscos), que sigue a I (Japonés).

Estado: solo lectura y este documento. No se creó ni cambió ningún archivo de código, no hay migración y no se toca otra plantilla. No se hizo `git add`, commit ni rama. No se tocó `.env`, `public/hot`, `public/build`, backups ni el `.;.swp`. El único cambio sin confirmar en el árbol (`tests/Feature/WebpImageServiceChallengerTest.php`) viene de antes y no es mío.

## Clave y nombres

- Clave **`k`** (`restaurantes.sitio_plantilla` es `string(1)`, sin migración; `k` no se usa en `Catalogos`, `AparienciaSitio`, vistas ni pruebas). Nombre visible **Cortes premium**. Prefijo de clases `cor-`, variables `--cor-*`, clave de pedido `menuonl_cart_k_{slug}`. Las letras `a` y `c` siguen retiradas.
- Vistas: `view('sitio.k')` y `menu.plantillas.k`. `RecomendadosDelSitio::PLANTILLAS_CON_PRINCIPAL` no la incluye (no hay "platillo principal").

## Paleta y contraste del prototipo (medido con la fórmula WCAG de `layout.blade.php`)

Colores: papel `#EFEAE2`, tinta `#1A1614`, burdeos `#7A1E1E`, dorado `#C9A46E`; auxiliares `#CFC6B8` (bordes), `#D8CFC1`, `#3A322D` (filas sobre tinta), `#4B4038`, `#5B4F45`, `#6B5F52` (texto suave), `#B3A898` (texto suave sobre tinta), `#A89C8C` (borde de campos), `#A07A45` (borde del logo), `#2F5A3A` / `#3E8C55` (abierto), `#9FD3AE` / `#5FBF7A` (abierto sobre tinta), `#F2DCDC`.

| Par | Contraste | Veredicto |
|---|---|---|
| tinta sobre papel / blanco | 15.00 / 17.97 | bien |
| papel sobre tinta | 15.00 | bien |
| burdeos sobre papel (texto: precios, enlaces) | 8.63 | bien |
| blanco sobre burdeos (botón Reservar, barra del pedido) | 10.33 | bien |
| `#F2DCDC` sobre burdeos | 7.90 | bien |
| dorado sobre tinta (etiquetas, precios, enlace en la banda de cortes) | 7.72 | bien |
| **dorado sobre papel** | **1.94** | **falla** |
| **dorado sobre blanco** | **2.33** | **falla** |
| `#6B5F52` / `#4B4038` / `#5B4F45` sobre papel | 5.18 / 8.39 / 6.62 | bien |
| `#B3A898` sobre tinta / sobre `#2A2320` | 7.68 / 6.60 | bien |
| `#2F5A3A` sobre papel; `#9FD3AE` sobre tinta (Abierto) | 6.63; 10.61 | bien |
| **hover global `a:hover{color:#7A1E1E}`** sobre tinta (enlace dorado, pie, botón "Reservar mesa" tinta/papel) | **1.74** | **falla** |
| **hover burdeos sobre el botón "Reservar" burdeos** | **1.00** (texto = fondo) | **falla grave: el botón se vacía** |
| **borde de campos `#A89C8C`** (nota, radios) sobre blanco / papel | **2.69 / 2.25** (pide 3:1) | **falla** |
| borde `#CFC6B8` sobre papel (separadores) | 1.41 | decorativo, no es campo |
| punto verde `#3E8C55` sobre papel | 3.45 | decorativo, siempre con palabra |
| tinta sobre fondo `#2A2320` de portada: marcador `#CFC6B8` | 9.14 | bien (el marcador se omite) |

### Correcciones propuestas (se confirman midiendo las variables reales de la hoja, con una prueba como la de J)

- `--cor-oro` `#C9A46E`: **solo sobre tinta** (7.72) o como adorno sin texto (filetes, borde del logo). Nunca como texto sobre papel o blanco.
- `--cor-oro-texto` nuevo, **`#7A5C2E`**: el dorado oscuro para cualquier texto "dorado" sobre claro. 5.16 sobre papel, 6.18 sobre blanco. (`#846233` da 4.64 sobre papel: pasa por poco; elijo el más holgado.)
- `--cor-borde-campo` nuevo, **`#857868`**: borde de la nota, radios y campos. 3.59 sobre papel y 4.30 sobre blanco. Los campos de la reserva son blancos sobre burdeos (10.33), así que ahí el límite ya cumple.
- Hover: ninguno cambia a burdeos sobre fondos oscuros ni sobre burdeos. Sobre claro, subrayado (el burdeos sobre papel pasa: 8.63); sobre tinta, el hover pasa a papel (15.00); botones, cambio de fondo con par medido.
- Estado **Cerrado** (el prototipo solo define Abierto): sobre papel, burdeos `#7A1E1E` (8.63); sobre tinta, `#E8A0A0` (8.53). Siempre con la palabra y un punto distinto, no solo color. Error de la reserva sobre burdeos: `#E8A0A0` sobre burdeos da 4.91; un rojo así se mide al construir.
- Acento del restaurante (`color_acento`): como en I y J, `colorAcentoSeguro()` / `colorAcentoTexto()`, solo en fondos con texto blanco y solo si llega a 4.5:1; sin acento propio, burdeos. El prototipo lo usaba también como color de texto (precios, "al carbón"): los precios van siempre en `--cor-burdeos` o `colorAcentoTexto()`. Las tres opciones del `data-props` eran un editor del lienzo, no un dato de Menuonl.

## Datos: de dónde sale cada bloque

Leyenda: **hay** = campo existente; **derivado** = se calcula con datos existentes; **omito** = el prototipo trae algo que Menuonl no tiene y no se lleva; **falta** = no existe y NO se inventa.

### Sitio (`/{slug}`)

| Bloque del prototipo | Campo / relación de Menuonl | Estado y lo que se construye |
|---|---|---|
| Logo: cuadro con borde dorado y la letra "C" | `logo`, `nombre` | Cambio. Con logo, el logo real con tamaño máximo fijo (`object-fit: contain`); sin logo, solo el nombre. La letra sería un logo inventado. El filete dorado se conserva como adorno solo alrededor del logo real |
| Nombre "Corte Norte" | `nombre` | hay |
| Subtítulo "Asador · Hermosillo" | n/a | **omito / falta**: no hay giro ni ciudad en la tabla (no existe columna `ciudad`). Se omite; no se deriva de la dirección |
| Navegación: Cortes, Galería, Horario, Reservar | `destacados`, `galeria_fotos`, `horarios`, `direccion`, `mapa_url`, `reservas_activas` | "Recomendados" solo con destacados; "Galería" solo con fotos; "Horario" solo con algún día abierto, dirección o mapa; "Reservar" solo con reservas activas; "Menú" siempre. "Cortes" no se usa: el restaurante puede no vender cortes |
| Pastilla "Abierto ahora · cierra 11:30 p. m." | `esta_abierto`, `horarios` | derivado. Solo "Abierto ahora" / "Cerrado ahora" (palabra + punto distinto), solo si hay algún día abierto. Sin "cierra X" |
| H1 "Cortes madurados, al carbón" (88 px, con cursiva de acento) | `nombre` | **omito**: la frase es de venta y afirma algo que Menuonl no sabe. El h1 es el nombre. Sin cursiva (ver Fuentes). Tamaño con `clamp()` y `overflow-wrap`: 88 px fijos desbordan a 360 px |
| Párrafo "Res sonorense seleccionada, madurada en casa…" | `eslogan`, `descripcion` | derivado de campos: eslogan y descripción, cada uno omitido si está vacío |
| (no está en el prototipo) "Hoy: horario" | `horarios`, zona horaria | Se agrega como en I y J. Se omite si hoy está cerrado |
| Botón "Reservar mesa" | `reservas_activas` | Solo con reservas activas |
| Botón "Ver menú" | `menu_url`, `pedidosHabilitados()` | "Ver menú y pedir" con pedidos habilitados; si no, "Ver menú" |
| Foto de portada 21:9 (marcador "Foto de portada") | `portada` | Foto real recortada 21:9. Sin portada se omite el bloque (sin marcador de texto). Alt: "Foto de {nombre}" |
| Banda tinta "La carta de cortes" + "Del asador a tu mesa" + lista de 5 cortes | `destacados` (hasta 6, sin agotados) | Título **"Recomendados"** ("carta" está prohibido; "Del asador a tu mesa" es frase de venta). Lista de filas como el prototipo: nombre, descripción, precio vigente, precio anterior tachado, etiquetas. Enlace "Ver el menú completo" a `menu_url`. Sin destacados se omite la banda |
| Columna "450 g" / "1.2 kg" por corte | n/a | **falta**: `productos` no tiene peso/gramaje. Se omite la columna (si el dueño lo escribe, va en el nombre o la descripción) |
| Galería (3 cuadros 4:5 de color) | `galeria_fotos` (`SitioPublicoDatos`, ya existe) | Fotos reales en el orden `orden`, `id`, recorte 4:5. Alt = descripción escapada; si está vacía, "Foto de {nombre}". Se omite sin fotos. Los cuadros de color no se llevan |
| Horario (3 filas agrupadas: "Lunes a jueves"…) | `horarios` | Cambio. Menuonl guarda siete días: se muestran los 7, lunes primero, hoy marcado, sin agrupar |
| Visítanos: dirección, Cómo llegar, WhatsApp, Llamar | `direccion`, `mapa_url`, `whatsapp_digitos`, `telefono_enlace`, `instagram_url`, `facebook_url` | Cada pieza se omite si falta su dato; se suman Instagram y Facebook si existen. Sin dirección el título pasa a "Contacto". Sin horario ni contacto no se dibuja la sección |
| Reservar (banda burdeos): fecha, hora, personas, nombre, teléfono, "Enviar reserva" | Formulario real (`sitio.js`, `data-url`, `data-disp`) | Cambio. La hora es una lista de franjas libres que llena `sitio.js` tras elegir fecha (el `type="time"` libre no sirve: el servidor solo acepta franjas libres). Se suman nota opcional, campo trampa, aviso de privacidad y mensaje de estado. Botón "Solicitar reserva". El panel sigue en burdeos con campos blancos |
| "Te confirmamos por WhatsApp." | n/a | **omito**: no es lo que hace el flujo |
| Pie "Corte Norte · Hecho con Menubly" | `nombre`, `config('app.name')` | Nombre, enlaces internos y "Sitio creado con {app}" |

### Menú (`/{slug}/menu`)

| Bloque del prototipo | Campo / relación de Menuonl | Estado y lo que se construye |
|---|---|---|
| Cabecera tinta, logo "C" y nombre | `logo`, `nombre` | Logo real con tamaño máximo fijo o solo el nombre |
| "Abierto" con punto verde | `$estaAbierto` | "Abierto" / "Cerrado" siempre con texto |
| H1 "Menú" | Texto fijo | "Menú". Nunca "Carta" |
| Selector de idioma (no está) | `idiomasDisponibles` | Se agrega como en I/J, solo con más de un idioma |
| Navegación de categorías (Cortes, Entradas, Guarniciones, Bebidas) | `categorias` visibles con platillos | Las del restaurante, no las del prototipo. Anclas `#categoria-N`; la categoría a la vista lleva `aria-current` y subrayado burdeos. Solo con más de una categoría. Desplazamiento horizontal con foco visible |
| Título de categoría y filas (nombre, precio, descripción) | traducciones, `precioVigente()`, `FormatoMoneda` | Igual que el prototipo, con precio anterior tachado y etiquetas |
| Foto de platillo | `imagen` | **El prototipo no trae foto en el menú.** Propuesta: si el producto tiene imagen, miniatura de 64 px; si no, nada (sin recuadro vacío). Ver decisiones |
| Variantes | `producto_variantes` | **falta en el prototipo**. Cada variante en su línea con su precio y su "+" |
| Agotado | `disponible`, `mostrar_agotados` | **falta en el prototipo**. Etiqueta "Agotado" con texto y sin botón |
| Botón "+" 44 px | Datos del servidor | Nombre accesible "Agregar {platillo} al pedido"; sin JavaScript queda oculto y se ofrece el enlace de WhatsApp |
| Tarjeta "Tu pedido" en la página (entrega, líneas, "Término y notas", total) | Controlador de pedido compartido | Cambio. Pasa a **diálogo** `<dialog>` con líneas, nota por línea de 80 caracteres, `<x-sitio.entrega>` (Domicilio / Para recoger), "Notas para la cocina" (el prototipo dice "Término y notas"; el término es de ese giro, el campo es genérico) y total. Las líneas de ejemplo y "Término medio" no se llevan |
| Barra inferior fija "Enviar por WhatsApp · 2 · $900" | Estado del pedido en `localStorage` | Barra fija "Ver pedido" con cuenta y total que abre el diálogo; el envío vive dentro. En el prototipo es `absolute` solo por el lienzo; aquí `fixed` |
| Aviso de cerrado | `pedidosHabilitados()`, `$estaAbierto` | **falta en el prototipo**: "Estamos cerrados ahora…" y sin "+" |
| Horario, contacto y pie | `SitioPublicoDatos` | **falta en el prototipo**; van al final del menú como en I/J |
| Ancho fijo 390 px y alto 2300 px | n/a | Cambio: ancho fluido con contenedor centrado (máx. ~720 px), alto automático; el 390 era el lienzo |
| `?mesa=` | n/a | Se ignora por completo (sin insignia, sin formulario, sin mensaje) |

## Textos de la portada

Se derivan de campos existentes, sin migración ni campos nuevos:

- h1 = `nombre`. Debajo, `eslogan` y `descripcion`, cada uno omitido si está vacío.
- Pastilla de estado y "Hoy: …" = `horarios` + zona horaria del restaurante.
- Botones y enlaces según `pedidosHabilitados()`, `reservas_activas` y datos de contacto.
- Rótulos fijos que no afirman nada del negocio: "Recomendados", "Ver el menú completo", "Galería", "Horario", "Visítanos", "Contacto", "Reserva tu mesa", "Solicitar reserva", "Tu pedido", "Menú".
- Sin testimonios, sin "madurados al carbón", sin "res sonorense", sin "te confirmamos por WhatsApp", sin "cierra X".

## Cambios respecto al prototipo, y por qué

1. **Logo real** con tamaño máximo fijo, o el nombre; no la inicial "C".
2. **H1 = nombre**, sin la frase ni la cursiva: no existe el dato y la cursiva exigía otra fuente.
3. **"La carta de cortes" a "Recomendados"**: "carta" prohibido y Menuonl no sabe que se venden cortes. Se omite la columna de gramaje.
4. **Subtítulo "Asador · Hermosillo"** omitido: sin giro ni ciudad en los datos.
5. **Estado** sin "cierra X"; Cerrado con color y palabra propios.
6. **Horario** de 7 días sin agrupar, hoy marcado.
7. **Reserva real** (franjas libres, nota, privacidad), botón "Solicitar reserva", sin "Te confirmamos por WhatsApp".
8. **Pedido en diálogo** con barra fija; variantes, agotados, aviso de cerrado, horario/contacto y pie que el prototipo no tiene.
9. **Contraste**: dorado de texto oscuro sobre claro, borde de campos, hovers y Cerrado (ver tabla). Los hovers del prototipo dejaban ilegible el botón "Reservar".
10. **Responsive**: h1 con `clamp()`, menú fluido; el prototipo de sitio no cabe a 360 px.
11. **Marcadores de foto** y cuadros de relleno omitidos; no se copian fotos.
12. **Acento** del restaurante (`color_acento`), no del editor del lienzo.
13. **`?mesa=`** ignorado.

## Pedido por WhatsApp: opción A (confirmada)

Copia y adaptación de `sitio-mariscos-pedido*.js`, sin tocar `pedido/controlador.js` ni `pedido/mensaje.js` (compartidos):

- `resources/js/sitio-cortes-pedido.js`: `iniciarPedido('cor', 'menuonl_cart_k')`; clave real `menuonl_cart_k_{slug}` (la vista escribe `data-clave`).
- `resources/js/sitio-cortes-pedido-mensaje.js`: `export * from './pedido/mensaje.js'`.
- `resources/js/sitio-cortes.js`: categoría a la vista (`aria-current`) y `prefers-reduced-motion`; solo con más de una categoría.
- Sin `innerHTML` ni `onclick`; líneas desde `<template>`. `localStorage` guarda solo ids, cantidades y notas.
- `<x-sitio.entrega prefijo="cor" clase-campo="cor-campo" clase-etiqueta="cor-campo__etiqueta" />`, sin tocar el componente.
- Respeta `pedidos_activos`, `whatsapp_pedidos` (8 a 20 dígitos) y "abierto ahora".

## Fuentes

Autoalojadas en `public/fonts/sitio/`, ambas OFL, subconjunto latín + acentos del español con `fonttools` (mismo conjunto de I/J: U+0020–007E, vocales acentuadas, ü, ñ y mayúsculas, ¿ ¡ « » ° · ×, comillas, guiones, puntos suspensivos, espacios finos, €), sin hinting, woff2. `fonttools` 4.68 y `brotli` ya están instalados (Python 3.15); no hay que instalar nada, solo bajar los TTF de `google/fonts` una vez.

El prototipo carga Cormorant 500/600/700 y Manrope 400/500/600/700, pero solo usa **Cormorant 600 y 700** (h1, h2, nombres, precios) y **Manrope 400 y 700** como pesos reales; el 600 de Manrope aparece en enlaces de navegación y el 500 no se usa.

| Archivo previsto | Origen | Peso |
|---|---|---|
| `cormorant-garamond-cortes-latin-600.woff2` | Cormorant Garamond estática 600 (`ofl/cormorantgaramond/`) | por medir |
| `cormorant-garamond-cortes-latin-700.woff2` | Cormorant Garamond estática 700 | por medir |
| `manrope-latin-400.woff2` | Instancia 400 de `Manrope[wght].ttf` (`ofl/manrope/`) | por medir |
| `manrope-latin-700.woff2` | Instancia 700 | por medir |
| `OFL-manrope.txt`, `OFL-cormorant-garamond-cortes.txt` | copiadas sin cambios del origen | texto |

Estimación por analogía con J (9 a 12 KB por archivo): **unos 36 a 44 KB en total**; el peso final exacto y el `sha256sum` de los cuatro archivos (deben ser distintos entre sí y distintos de los existentes) los reporto en la fase 2.

Decisiones:

- **Manrope 600 y 500** caen en 400/700 (el 600 en 700). Dos archivos menos, sin cambio apreciable. Conservar 600 suma unos 10 KB.
- **Cursiva de Cormorant**: no se necesita (se elimina la cursiva del h1).
- **Cormorant ya existe en el repositorio** (`cormorant-garamond-latin-500-normal/-500-italic/-600-normal.woff2`, `OFL-cormorant.txt`, declaradas en `fuentes.css`). Esos archivos son de otra plantilla, no incluyen el 700 y están compartidos; para que K sea autónoma (decisión de J con DM Serif) se genera un subconjunto propio con otro nombre y no se toca nada existente. Hay que verificar que su `sha256` no coincida con el de los existentes si el 600 resulta idéntico en bytes; si coincidiera, se reporta, no se reutiliza.
- `font-display: swap`, `preload` solo de Cormorant 700 (h1).

## Qué se toca fuera de la plantilla (a registrar; lo reporto)

1. `app/Services/Catalogos.php`: `k` en `plantillasSitio()`.
2. `app/Services/AparienciaSitio.php`: ficha `k` en `fichas()` (nombre "Cortes premium", colores de la hoja) y `k` en `PLANTILLAS_CON_GALERIA` (`['b','i','j','k']`). **No** va en `PLANTILLAS_CON_ESTILO` (`['b','d']`).
3. `vite.config.js`: `resources/css/sitio/k.css` y los scripts de entrada.
4. `lang/es/panel.php`: `apariencia.estilo.descripcion` suma "Cortes premium" a la lista de plantillas con colores propios. El aviso de Fotos se arma desde `PLANTILLAS_CON_GALERIA`; se verifica en `PanelFotosDelRestauranteTest`, que se ajusta.
5. Pruebas existentes que se tocan solo para sumar `k` a listas de claves: `SitioPizzeriaTest`, `SitioBarTest`, `SitioCafeteriaTest`, `SitioPastelTest`, `SitioEditorialOscuroTest` (si lista), `SitioJaponesTest`, `SitioMariscosTest`, `PanelAparienciaTest`, `TextosPrePilotoTest`, `PlanSitioPublicoTest` y `PanelFotosDelRestauranteTest`.

Sin cambios: `SitioPublicoDatos` (ya tiene `galeria_fotos`), `MenuPublicoController`, `Restaurante`, `sitio.js`, `menu.js`, `menuonl.css`, `<x-sitio.entrega>`, `<x-sitio.imagen>`, `pedido/controlador.js`, `pedido/mensaje.js`, `.env`, `public/hot`, `public/build`, backups y las plantillas b a j.

### Ficha propuesta para `fichas()['k']`

- nombre: "Cortes premium".
- descripción: "Papel cálido, tinta oscura, burdeos y dorado, con tipografía clásica. Menú en lista." (para el catálogo: "Papel cálido, tinta oscura, burdeos y dorado. Para asadores, steakhouses y cortes premium.")
- para: "Asadores y cortes premium".
- colores leídos de la hoja y comprobados por prueba: `--cor-papel`, `--cor-tinta`, `--cor-burdeos`, `--cor-oro`.

## Archivos que se crearán en fase 2

- Vistas: `resources/views/sitio/k.blade.php`, `resources/views/menu/plantillas/k.blade.php`, `resources/views/components/sitio/cortes/{layout,cabecera,visita,reservar,pedido,pie}.blade.php`.
- Hoja `resources/css/sitio/k.css`; scripts `sitio-cortes.js`, `sitio-cortes-pedido.js`, `sitio-cortes-pedido-mensaje.js`.
- Fuentes y licencias en `public/fonts/sitio/`.
- Pruebas: `SitioCortesTest`, `PedidoWhatsappCortesTest`, `ReservasCortesTest`, `tests/Js/pedido-cortes.test.mjs`, `tests/Js/pedido-mensaje-cortes.test.mjs`.

## Plan de pruebas (con lo aprendido)

- Toda prueba que dependa de abierto/cerrado, "Hoy:" o "Cerrado ahora" fija la hora con `travelTo` en la zona del restaurante.
- Datos que caben en su columna (`color_acento` de 7 caracteres; `whatsapp_pedidos` de 8 a 20 dígitos; lo largo en memoria), `$modelo->id` en lugar de ids fijos, sin depender de tablas vacías.
- `withoutVite()` en el `setUp` de las pruebas que leen `public/build`.
- `SitioCortesTest`: catálogo, ficha, elegible en el panel, sitio y menú completos, abierto/cerrado, galería (orden, alt escapado, "Foto de {nombre}", sin fotos), `?mesa=` ignorado, secciones omitidas, logo con y sin imagen, `color_acento` hostil y legible, escape, sin recursos externos ni emojis, enlaces con `route()`, "Carta" ausente, prefijo `cor-` sin choque con `menuonl.css`, colores solo en variables, contraste medido sobre las variables reales de `k.css` (texto 4.5:1, bordes de campo 3:1, incluido que `--cor-oro` no se use como texto sobre claro), fuentes (existen, licencias OFL, sha256 distintos) y movimiento reducido.
- **b, i y j no llevan marcas de k**: render de sitio y menú de `b`, `i` y `j` sin `cor-`, `sitio-k`, `data-cor-` ni `menuonl_cart_k`, y siguen en sus vistas (`assertViewIs`). Sin huellas SHA de archivos.
- Suma de `k` a las listas de las pruebas existentes (arriba) y de los `.mjs` nuevos por `PedidoWhatsappJsTest` (Node 22, sin carpetas, sin APIs de Node 24).
- Pint con `--dirty` y solo las pruebas necesarias antes de la suite final.

## Riesgos

- **Node local es 26.12**, no 22; no puedo confirmar el comportamiento en 22 desde este equipo.
- Navegador real a 360/390/768/1024/1360 px, diálogo del pedido, fotos reales y MariaDB: solo se verifican en navegador al terminar la fase 2 o en el CI.

## ¿Algo exige migración, cambiar datos o tocar otra plantilla?

No. `k` cabe en `string(1)`, `galeria_fotos` ya existe, `<x-sitio.entrega>` y el controlador de pedido se reutilizan, y lo único fuera de la plantilla es el registro y las listas de pruebas. No me detuve a preguntar.

## Decisiones tuyas (si no dices nada, aplico lo que va primero)

1. **Miniatura de platillo en el menú**: solo si el producto tiene foto, de 64 px; sin foto, nada (por defecto). Alternativa: menú solo texto como el prototipo.
2. **Dorado de texto sobre claro** `#7A5C2E` (por defecto) u otro tono que prefieras.
3. **Fuentes**: Cormorant 600 y 700 + Manrope 400 y 700 (por defecto); conservar Manrope 600 suma unos 10 KB.
4. **Subtítulo bajo el nombre** ("Asador · Hermosillo"): omitido (por defecto). Si lo quieres, exige un dato nuevo (migración), así que me detendría.
5. **Orden de la banda "Recomendados"**: si no hay destacados, no se dibuja la banda (por defecto).

## Resultado de las fases 2 y 3

- Construido: vistas (`sitio/k`, `menu/plantillas/k`, seis componentes en `components/sitio/cortes/`), hoja `k.css`, tres scripts (`sitio-cortes*.js`), fuentes y licencias, registro en catálogo, ficha, galería, Vite y `lang/es/panel.php`. Pedido con opción A (`menuonl_cart_k_{slug}`), sin `innerHTML` ni `onclick`.
- Decisiones aplicadas: miniatura de 64 px solo si el producto tiene foto (sin recuadro vacío); sin subtítulo bajo el nombre; sin banda de Recomendados si no hay destacados; Cerrado siempre con la palabra y un punto hueco; `?mesa=` ignorado.
- Fuentes (subconjunto latín + español, `fonttools`, sin hinting): `cormorant-garamond-cortes-latin-600.woff2` 12 344 B, `-700` 11 744 B, `manrope-latin-400.woff2` 8 416 B, `manrope-latin-700.woff2` 8 464 B. **Peso final: 40 968 B.** Los cuatro sha256 son distintos entre sí y de los Cormorant existentes (comprobado con `sha256sum` y por la prueba). Licencias `OFL-cormorant-garamond-cortes.txt` y `OFL-manrope.txt`, copiadas sin cambios de `google/fonts`.
- Contraste, medido por la prueba sobre las variables reales de `k.css`: oro de texto `#7a5c2e` 5.16 sobre papel y 6.18 sobre blanco; oro original solo sobre tinta (7.72) y la prueba exige que sea menor que 4.5 sobre papel y que solo se use como texto bajo `.cor-oscuro`; borde de campos `#857868` 3.59 sobre papel y 4.30 sobre blanco; blanco sobre burdeos 10.33; Cerrado `#7a1e1e` 8.63 sobre papel y `#e8a0a0` 8.53 sobre tinta; ningún hover pone burdeos u oro como texto.
- b, i y j no llevan marcas de k: `test_b_i_y_j_no_llevan_marcas_de_la_plantilla_k` (sin `cor-`, `sitio-k`, `data-cor-`, `menuonl_cart_k`), sin huellas de archivos.
- `vite build` a una carpeta temporal compila `k.css` y los scripts; `public/build` no se tocó.
