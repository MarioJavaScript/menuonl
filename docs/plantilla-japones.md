# Plantilla I "Japonés"

Prototipo aprobado: `C:\Users\thexl\Desktop\proyectos\plantilla-japones` (`Japones-Sitio.dc.html`, escritorio 1280 px; `Japones-Menu.dc.html`, celular 390 px). Solo se tomó como referencia visual: estilos inline y datos de `renderVals()`. El runtime del lienzo (`support.js`, `<x-dc>`, `<sc-for>`, `data-props`) se ignoró. Modelo técnico: plantilla h (Pizzería).

## Clave y nombres

- Clave **`i`** (`restaurantes.sitio_plantilla` es `string(1)`; sin migración). Nombre visible **Japonés**. Prefijo de clases `jap-`, variables `--jap-*`. No se reutilizan `a` ni `c`.
- Autónoma: tiene su propio sitio y menú, no usa `menuonl.css`, Tailwind ni el estilo (tema) del restaurante, y no cambia ninguna otra plantilla.

## Archivos

- Vistas: `resources/views/sitio/i.blade.php`, `resources/views/menu/plantillas/i.blade.php` y `resources/views/components/sitio/japones/{layout,cabecera,visita,reservar,pedido,pie}.blade.php`.
- Hoja: `resources/css/sitio/i.css`.
- Scripts: `resources/js/sitio-japones.js` (categoría visible), `sitio-japones-pedido.js` y `sitio-japones-pedido-mensaje.js`.
- Fuentes y licencias: `public/fonts/sitio/` (ver "Fuentes").
- Pruebas: `SitioJaponesTest`, `PedidoWhatsappJaponesTest`, `ReservasJaponesTest`, `tests/Js/pedido-japones.test.mjs` y `tests/Js/pedido-mensaje-japones.test.mjs`.

## Paleta (variables en `:root` de `i.css`)

Del prototipo, salvo el borde de los campos y el color de error, que se agregaron para cumplir contraste. Cifras calculadas sobre la hoja; la prueba `test_los_pares_de_texto_de_la_hoja_llegan_a_4_5_de_contraste` las vigila.

| Variable | Valor | Uso | Contraste |
|---|---|---|---|
| `--jap-papel` | `#f6f5f1` | Fondo | n/a |
| `--jap-blanco` | `#ffffff` | Banda de recomendados, diálogo del pedido, campos | n/a |
| `--jap-tinta` | `#151515` | Texto, banda de horario, botón Reservar | 16.74:1 sobre papel |
| `--jap-rojo` | `#c8281e` | Acento base: botones, kanji, precios | 5.10:1 sobre papel; 5.56:1 con blanco encima |
| `--jap-texto-suave` | `#55524c` | Descripciones | 7.14:1 sobre papel |
| `--jap-texto-portada` | `#45433f` | Descripción de la portada | 9.05:1 sobre papel |
| `--jap-verde` | `#1f5f3f` | "Abierto", botón verde | 6.96:1 sobre papel |
| `--jap-claro-sobre-tinta` | `#c9c6be` | Horas en la banda oscura | 10.70:1 sobre tinta |
| `--jap-error` | `#a61b12` | Mensaje de error de la reserva | 7.54:1 sobre blanco |
| `--jap-borde-campo` | `#76726a` | Borde de campos (en el prototipo era `#a8a49a`, 2.4:1) | 4.79:1 sobre blanco |
| `--jap-linea`, `--jap-recuadro` | `#d9d6ce`, `#e4e1d8` | Separadores y recuadro sin foto (decorativos) | n/a |

Colores solo en variables: ni la hoja fuera de `:root` ni las vistas llevan `#hex`, `rgb()` ni `hsl()`. Los derivados del acento (hover, capa) van en `.jap-pagina`, porque la vista inyecta `--jap-acento` en `<body>`.

## Datos: de dónde sale cada bloque

### Sitio (`/{slug}`)

| Bloque | Campo / relación de Menubly | Lo construido |
|---|---|---|
| Cabecera | `logo`, `nombre` | Con logo, el logo (2.75 rem, `object-fit: contain`) junto al nombre; sin logo, solo el nombre. La inicial en círculo del prototipo no se usa (sería un logo inventado) |
| Navegación | `menu_url`, `galeria`, `horarios`, `direccion`, `mapa_url`, `reservas_activas` | "Menú" siempre; "Galería" solo con fotos; "Horario" solo si hay horario abierto, dirección o mapa; "Reservar" solo con reservas activas |
| Kanji 和食 | Texto decorativo fijo, `aria-hidden` | Vertical junto al título. Sustituye a 寿司 del prototipo: "comida japonesa" sirve para sushi, ramen o cualquier cocina japonesa |
| Estado | `esta_abierto` y `horarios` | "Abierto ahora" / "Cerrado ahora", con texto y no solo color. Solo si hay algún día abierto. Se omite "cierra X": con varios turnos no se sabe cuál cierra |
| H1 | `nombre` | El prototipo trae una frase de venta que Menubly no tiene; el h1 es el nombre |
| Eslogan y descripción | `eslogan`, `descripcion` | Cada uno se omite si está vacío |
| "Hoy: horario" | `horarios`, zona horaria del restaurante | Se omite si hoy está cerrado |
| Botones de portada | `menu_url`, `pedidosHabilitados()`, `reservas_activas` | "Ver menú y pedir" con pedidos habilitados; si no, "Ver el menú". "Reservar mesa" solo con reservas activas |
| Foto de portada y círculo de acento | `portada` | Sin portada se omiten foto y círculo y el texto ocupa el ancho. El círculo queda detrás, sin texto encima |
| Recomendados | `destacados` (hasta 6, sin agotados) | Título "Recomendados" (el prototipo decía "Lo que más se pide", que afirma ventas que Menubly no conoce). Foto circular o recuadro neutro, nombre, precio vigente, precio anterior tachado, etiquetas y descripción. Con variantes, el precio base |
| Galería | `galeria_fotos` (de `SitioPublicoDatos`) | Fotos en el orden `orden`, `id`. Alt = descripción escapada; sin descripción, "Foto de {nombre}". Se omite sin fotos |
| Horario y Visítanos | `horarios`, `direccion`, `telefono`, `mapa_url`, `whatsapp_digitos`, `telefono_enlace`, `instagram_url`, `facebook_url` | Una banda tinta. Horario con los 7 días (lunes primero, hoy marcado, sin agrupar). Dirección, teléfono y botones Cómo llegar, WhatsApp, Llamar, Instagram y Facebook; cada pieza se omite si falta su dato. Si no hay dirección, el título pasa a "Contacto" |
| Reserva | Formulario real (`sitio.js`, `data-url`, `data-disp`) | "Reserva tu mesa" y el mismo formulario que h: la hora es una lista de franjas libres del servidor. No se lleva "Te confirmamos por WhatsApp" porque no es lo que hace el flujo |
| Pie | `nombre` | Nombre, enlaces internos y "Sitio creado con Menubly" |

### Menú (`/{slug}/menu`)

| Bloque | Campo / relación de Menubly | Lo construido |
|---|---|---|
| Cabecera | Igual que el sitio, más `$estaAbierto` y `?mesa=` validado | "Abierto" / "Cerrado" con texto. La mesa solo sale como insignia en la cabecera; no llega al formulario ni al mensaje |
| Título | Texto fijo | "Menú" y el kanji 品書 (`aria-hidden`). Nunca "Carta" |
| Eslogan e idiomas | `eslogan`, `idiomasDisponibles` | El selector de idioma solo con más de un idioma |
| Barra de categorías | `categorias` visibles con platillos | Enlaces ancla `#categoria-N` con subrayado de acento en la categoría a la vista. Solo con más de una categoría. Sin JavaScript siguen funcionando como enlaces; `sitio-japones.js` solo marca `aria-current` |
| Fila de platillo | `productos`, traducción, `imagen`, `precioVigente()`, `FormatoMoneda` | Foto circular de 72 px o recuadro neutro circular, nombre, precio, precio anterior, etiquetas y descripción |
| Variantes | `producto_variantes` | Cada variante en su línea con su precio y su propio "+" |
| Botón "+" | Datos del servidor | 44 px, nombre accesible "Agregar {platillo} al pedido"; sin JavaScript queda oculto |
| Agotado | `disponible`, `mostrar_agotados` | Etiqueta "Agotado" con texto y sin botón |
| Pedido | Controlador compartido | Barra fija inferior ("Ver pedido", cuenta y total) y diálogo "Tu pedido" con líneas, nota por línea de 80 caracteres, Domicilio / Para recoger, "Notas para la cocina" y total. Se omite si no se puede pedir (pedidos desactivados, sin número, cerrado o sin modalidades) |
| Cerrado | `pedidosHabilitados()`, `$estaAbierto` | Aviso "Estamos cerrados ahora…" y sin botones "+" |
| Horario, contacto y pie | Los mismos de `SitioPublicoDatos` | La banda "Horario / Visítanos" y el pie van al final del menú |

Lo que no se lleva del prototipo: la inicial "K" como logo, "cierra X", "Lo que más se pide", agrupar días del horario, "Te confirmamos por WhatsApp" y la tarjeta "Tu pedido" dentro de la página (va en el diálogo, como en h). Nombres, precios, direcciones y platillos de "Sushi Kaze" son de ejemplo y no se llevan.

## Textos de la portada

Se derivan de los campos existentes, igual que h: el h1 es el nombre y debajo van el eslogan y la descripción, cada uno omitido si está vacío; el estado y "Hoy: …" se calculan. Sin migración ni campos nuevos. Rótulos fijos de interfaz: "Recomendados", "Ver el menú completo", "Galería", "Horario", "Visítanos", "Contacto", "Reserva tu mesa", "Tu pedido". Ninguno afirma algo del negocio.

## Pedido por WhatsApp

Opción A: se copia y adapta el patrón de h. `sitio-japones-pedido.js` inicia el controlador compartido (`pedido/controlador.js`) con `iniciarPedido('jap', 'menuonl_cart_i')` y la clave `menuonl_cart_i_{slug}`; `sitio-japones-pedido-mensaje.js` reexporta el formato de mensaje compartido. Sin `innerHTML` ni `onclick`; las líneas salen de un `<template>`. `localStorage` guarda solo ids, cantidades y notas. La entrega es Domicilio / Para recoger con `<x-sitio.entrega>`; `?mesa=` no llega al formulario ni al mensaje. Se respeta `pedidos_activos`, `whatsapp_pedidos` (8 a 20 dígitos) y "abierto ahora".

`sitio-japones.js` solo se carga con más de una categoría, y respeta `prefers-reduced-motion`.

## Fuentes

Autoalojadas en `public/fonts/sitio/`, ambas con licencia OFL. Subconjunto: latín básico (U+0020–007E), acentos y signos del español (á é í ó ú ü ñ y mayúsculas, ¿ ¡ « » ° · ×, comillas, guiones, puntos suspensivos, espacios finos y €) y, solo en Shippori Mincho, los kanji 和 食 品 書. Sin hinting y con woff2.

| Archivo | Origen | Peso |
|---|---|---|
| `shippori-mincho-b1-latin-800.woff2` | `ShipporiMinchoB1-ExtraBold.ttf` (estático) | 12 516 B |
| `zen-kaku-gothic-new-latin-400.woff2` | `ZenKakuGothicNew-Regular.ttf` (estático) | 6 204 B |
| `zen-kaku-gothic-new-latin-700.woff2` | `ZenKakuGothicNew-Bold.ttf` (estático) | 6 204 B |
| **Total** | | **24 924 B** |

Los TTF salen de `google/fonts` (`ofl/shipporiminchob1/` y `ofl/zenkakugothicnew/`). Los dos pesos de Zen Kaku son archivos distintos que casualmente pesan lo mismo comprimidos: sus SHA-256 y su tabla `glyf` difieren, y no son fuentes variables (sin tabla `fvar`). Licencias: `OFL-shippori-mincho-b1.txt` y `OFL-zen-kaku-gothic-new.txt`, copiadas sin cambios del mismo repositorio. Una prueba comprueba que los tres woff2 existen, que pesan menos de 60 KB en total y que cada licencia incluye el texto de la SIL Open Font License.

## Panel

- Ficha en `AparienciaSitio::fichas()['i']`: nombre "Japonés", "Papel claro, tinta negra y rojo japonés, con letras de pincel y fotos redondas.", para "Restaurantes japoneses, de sushi y de ramen". Sus cuatro colores salen de la hoja (`--jap-papel`, `--jap-tinta`, `--jap-rojo`, `--jap-recuadro`) y una prueba lo comprueba.
- Catálogo: `Catalogos::plantillasSitio()['i']`.
- No está en `PLANTILLAS_CON_ESTILO` (sigue `['b', 'd']`): no muestra el selector "Estilo del menú". Sí está en `PLANTILLAS_CON_GALERIA` (`['b', 'i']`).
- Aviso de `lang/es/panel.php` (`apariencia.estilo.descripcion`): "…los diseños Cafetería, Bar, Cafetería pastel, Pizzería y Japonés traen sus propios colores." El aviso de Fotos para b a h pasa a "Se ven en los diseños Oscuro elegante y Japonés."
- Acento (`color_acento`): `colorAcentoSeguro()` / `colorAcentoTexto()` solo cambian fondos (botones, subrayado de categoría, círculo de portada, barra del pedido) y solo si el par llega a 4.5:1. Precios y kanji usan siempre `--jap-rojo`. `AparienciaSitio::estadoDelColor()` ya cubre `i` por la rama general.

## Qué se tocó fuera de la plantilla

1. `app/Services/SitioPublicoDatos.php`: clave nueva `galeria_fotos` (`imagen` y `descripcion`, mismo orden); `galeria` no cambia y b sigue igual (hay prueba).
2. `app/Services/Catalogos.php`: `i` en `plantillasSitio()`.
3. `app/Services/AparienciaSitio.php`: ficha `i` y `i` en `PLANTILLAS_CON_GALERIA`.
4. `vite.config.js`: `i.css` y los dos scripts de entrada.
5. `lang/es/panel.php`: aviso del estilo del menú.
6. Pruebas existentes: las cinco `Sitio*Test` (lista de claves), `PanelAparienciaTest`, `TextosPrePilotoTest`, `PlanSitioPublicoTest` (suma `i` y `withoutVite()` en su `setUp`, porque leía `public/build`) y `PanelFotosDelRestauranteTest` (tres aserciones de galería).

Sin cambios: `MenuPublicoController`, `Restaurante`, `sitio.js`, `menu.js`, `menuonl.css`, `<x-sitio.entrega>`, `<x-sitio.imagen>`, las plantillas b a h y los temas. Sin migraciones ni cambios de datos.

## Pruebas

`SitioJaponesTest` cubre: catálogo, ficha, elegible en el panel, sitio y menú completos, abierto y cerrado ahora, galería (orden, alt escapado, sin fotos, b sin cambios), mesa, las otras plantillas sin cambios, secciones omitidas, recuadro sin foto, logo con y sin imagen, `color_acento` hostil y legible, escape de textos, sin recursos externos ni emojis, enlaces con rutas con nombre, "Carta" ausente, prefijo `jap-` sin choque con `menuonl.css`, colores solo en variables, contraste, fuentes y movimiento reducido. `PedidoWhatsappJaponesTest` y `ReservasJaponesTest` siguen a sus equivalentes de h. Las de Node usan el formato `*.test.mjs` que arma `PedidoWhatsappJsTest` (sin carpetas, compatible con Node 22). Ninguna depende de `public/build`.

Reglas de pruebas por lecciones del CI: los valores guardados caben en su columna (`color_acento` y `whatsapp_pedidos`; los valores largos se prueban en memoria), sin ids fijos (siempre `$modelo->id`) y sin depender de tablas vacías.

## Observación sobre una prueba de h

`SitioPizzeriaTest::test_el_mantel_es_solo_css_y_las_variantes_no_llevan_selector_de_precio` no fija la hora. Su restaurante abre los miércoles de 18:00 a 23:59 (Ciudad de México) y, abierto, el menú dibuja el diálogo del pedido con los radios Domicilio / Para recoger; la prueba exige que no haya ningún `<input type="radio"`. Falla solo en esa franja. No se modificó.

## Riesgos y límites

- **360 px**: el título y el kanji usan `clamp()`; sin desborde horizontal medido con Edge sin ventana, dentro de un marco de 360 px.
- **Día de hoy y "abierto ahora"**: se calculan en el servidor con la zona del restaurante; la página del sitio se cachea 30 s.
- **Sin verificar**: fotos reales (las capturas usaron rutas de prueba), el diálogo del pedido abierto y el foco en un navegador, y MariaDB (solo lo confirma el CI).
