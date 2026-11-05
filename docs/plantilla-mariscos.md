# Plantilla J "Mariscos" · Fase 1 (análisis, sin código)

Prototipos leídos: `C:\dev\plantilla-mariscos\Mariscos-Sitio.dc.html` (escritorio 1280 px) y `Mariscos-Menu.dc.html` (celular 390 px). Solo referencia visual: estilos inline y datos de `renderVals()`. El runtime del lienzo (`support.js`, `<x-dc>`, `<sc-for>`, `data-props`) se ignora. Modelo técnico: plantilla I (Japonés), que a su vez sigue a h.

Estado de esta fase: solo lectura y este documento. No se creó ni cambió ningún archivo de código, no hay migración y no se toca otra plantilla. No se hizo `git add`, commit ni rama.

## Clave y nombres

- Clave **`j`** (`restaurantes.sitio_plantilla` es `string(1)`, sin migración). Nombre visible **Mariscos**. Prefijo de clases `mar-`, variables `--mar-*`. Las letras `a` y `c` siguen retiradas y no se reutilizan.
- La vista del sitio sale de `view('sitio.'.$plantilla)` y la del menú de `view()->exists('menu.plantillas.'.$plantilla)`; basta con crear las vistas `j`. No hay listas de letras escritas en otro lado, salvo las ya conocidas (ver "Qué se toca fuera de la plantilla").
- `RecomendadosDelSitio::PLANTILLAS_CON_PRINCIPAL` (`e f g h`) no incluye `i` y tampoco incluirá `j`: Mariscos no destaca un "platillo principal".

## Paleta (del prototipo) y contraste medido

Colores del prototipo: marino `#0E2A3B`, papel `#F7F4EE`, coral `#C2492C`, blanco, y auxiliares `#1E4258` (marino claro: olas y círculo de portada), `#C8D6DF` (texto suave sobre marino), `#F4A68D` (coral claro sobre marino), `#4A6372` (texto suave sobre claro), `#C9D8DE` (bordes), `#E3DCCF` (línea del pie), `#9BE3A8` / `#BDEBC8` (punto y texto de "Abierto").

Cifras calculadas con la fórmula WCAG (la misma de `layout.blade.php`) sobre los valores del prototipo:

| Par | Contraste | Veredicto |
|---|---|---|
| papel sobre marino | 13.54 | bien |
| marino sobre papel / blanco | 13.54 / 14.86 | bien |
| `#C8D6DF` sobre marino / sobre `#1E4258` | 10.01 / 7.16 | bien |
| `#F4A68D` sobre marino | 7.56 | bien |
| `#BDEBC8` sobre marino | 11.24 | bien |
| `#4A6372` sobre papel / blanco | 5.76 / 6.33 | bien |
| blanco sobre coral `#C2492C` (botones) | 4.90 | bien |
| coral `#C2492C` como texto sobre **blanco** | 4.90 | bien |
| coral `#C2492C` como texto sobre **papel** (precios y "Ver el menú completo" en el prototipo) | **4.47** | **falla por 0.03** |
| coral `#C2492C` como texto sobre marino (hover de enlaces del prototipo) | **3.03** | **falla** |
| borde de campos `#C9D8DE` sobre blanco (nota del pedido, pastillas) | **1.46** | **falla** (pide 3:1) |
| borde de pastilla `#5C7C8F` sobre marino | 3.35 | bien (decorativo) |

Correcciones propuestas (se confirman al medir sobre las variables reales, con una prueba como la de Japonés):

- `--mar-coral` `#C2492C`: solo fondos (botones, pastilla activa, barra del pedido) con texto blanco. Se queda como en el prototipo.
- `--mar-coral-texto` nuevo, `#B84328`: para precios, enlaces y cualquier texto coral sobre claro. 4.94 sobre papel y 5.42 sobre blanco.
- `--mar-coral-claro` `#F4A68D`: único coral permitido como texto sobre marino. El `a:hover{color:coral}` del prototipo se sustituye por un hover que cumpla el par de su fondo.
- `--mar-borde-campo` nuevo, `#678190`: borde de campos y de la nota. 4.10 sobre blanco y 3.74 sobre papel. Los campos de la reserva van en blanco sobre marino (14.86), así que ahí el límite del campo ya cumple sin borde.
- El estado "Cerrado" necesita color propio sobre marino (el prototipo solo define "Abierto"): texto papel con punto distinto y siempre con la palabra, no solo color. El color de error de la reserva sobre marino se mide al construir (un rojo claro, no `#a61b12`).
- Acento del restaurante (`color_acento`): igual que en I, `colorAcentoSeguro()` / `colorAcentoTexto()`, solo en fondos y solo si el par llega a 4.5:1. Los precios y textos coral usan siempre `--mar-coral-texto`. Las tres opciones del prototipo (`#C2492C`, `#0E2A3B`, `#2F6B4F`) eran un editor del lienzo, no un dato de Menuonl.

## Datos: de dónde sale cada bloque

Leyenda: **hay** = el campo existe tal cual; **derivado** = se calcula con datos existentes; **omito** = el prototipo trae algo que Menuonl no tiene y no se lleva; **falta** = no existe y NO se inventa.

### Sitio (`/{slug}`)

| Bloque del prototipo | Campo / relación de Menuonl | Estado y lo que se construye |
|---|---|---|
| Logo: círculo papel con la letra "B" | `logo`, `nombre` | Cambio. Con logo, el logo real con tamaño máximo fijo (`object-fit: contain`) junto al nombre; sin logo, solo el nombre. La letra sería un logo inventado |
| Nombre "Mariscos La Bahía" | `nombre` | hay |
| Navegación: Menú, Galería, Horario, Reservar | `menu_url`, `galeria_fotos`, `horarios`, `direccion`, `mapa_url`, `reservas_activas` | "Menú" siempre; "Galería" solo con fotos; "Horario" solo con algún día abierto, dirección o mapa; "Reservar" solo con reservas activas. Igual que I |
| Pastilla "Abierto ahora · cierra 9:00 p. m." | `esta_abierto`, `horarios` | derivado. Solo "Abierto ahora" / "Cerrado ahora" (palabra, no solo color), solo si hay algún día abierto. Se omite "cierra X": con varios turnos no se sabe cuál cierra |
| H1 "Del mar de Cortés a tu mesa" (con "a tu mesa" en cursiva coral) | `nombre` | omito. La frase es de venta y de una región que Menuonl no conoce. El h1 es el nombre. Sin la cursiva, no hace falta DM Serif Display itálica (ver Fuentes) |
| Párrafo "Aguachiles, callo de hacha y camarón fresco…" | `eslogan`, `descripcion` | derivado de campos. Eslogan en coral claro (el lugar de la cursiva) y descripción debajo, cada uno omitido si está vacío |
| "Pide a domicilio o pasa a recoger." | `modalidadesDePedido()` / `textoDeModalidadesDePedido()` | omito del texto fijo. Es verdad solo si el restaurante tiene encendidas esas modalidades. Propuesta: no mostrarlo en la portada (ya sale en el diálogo del pedido) |
| (no está en el prototipo) "Hoy: horario" | `horarios`, zona horaria | Se agrega, como en I, dentro del bloque de portada. Se omite si hoy está cerrado |
| Botón "Ver menú y pedir" | `menu_url`, `pedidosHabilitados()` | "Ver menú y pedir" con pedidos habilitados; si no, "Ver el menú" |
| Botón "Reservar mesa" | `reservas_activas` | Solo con reservas activas |
| Círculo de foto de portada (marcador "Foto de portada") | `portada` | Foto real recortada en círculo de 480 px máx. Sin portada se omite el círculo y el texto ocupa el ancho; el marcador de texto del prototipo no se lleva |
| Olas SVG de la portada | Decorativo | Se conservan como `<svg aria-hidden>` en línea, con colores por variables (sin recurso externo) |
| "Lo más pedido" + cuatro tarjetas | `destacados` (hasta 6, sin agotados) | Título **"Recomendados"** (el prototipo afirma ventas que Menuonl no conoce). Tarjeta: foto 4:3 o recuadro neutro sin texto, nombre, precio vigente, precio anterior tachado, etiquetas y descripción. Con variantes, el precio base. Enlace "Ver el menú completo" a `menu_url` |
| Galería (cuatro cuadrados 1:1) | `galeria_fotos` (clave nueva en `SitioPublicoDatos`, ver abajo) | Fotos reales en el orden `orden`, `id`. Alt = descripción escapada; si está vacía, "Foto de {nombre}". Se omite sin fotos. Los cuadrados con color de relleno del prototipo no se llevan |
| Horario ("Martes a viernes", "Sábado y domingo", "Lunes") | `horarios` | Cambio. El prototipo agrupa rangos; Menuonl guarda siete días. Se muestran los 7 días, lunes primero, hoy marcado, sin agrupar (decisión de I). Se mantiene la sección blanca del prototipo (no la banda tinta de I) |
| Visítanos: dirección, Cómo llegar, WhatsApp, Llamar | `direccion`, `telefono`, `mapa_url`, `whatsapp_digitos`, `telefono_enlace`, `instagram_url`, `facebook_url` | Cada pieza se omite si falta su dato. Se suman Instagram y Facebook si existen (el prototipo no los tiene, pero el dato sí). Sin dirección el título pasa a "Contacto". Si no hay horario ni contacto, la sección no se dibuja |
| Reservar: fecha, hora, personas, nombre, teléfono y "Enviar reserva" | Formulario real (`sitio.js`, `data-url`, `data-disp`) | Cambio. El prototipo usa `type="time"` libre; el servidor solo acepta franjas libres, así que la hora es una lista que llena `sitio.js` tras elegir la fecha. Se suman nota opcional, campo trampa, aviso de privacidad y mensaje de estado, igual que I/h. Botón **"Solicitar reserva"** (la reserva no queda confirmada al instante). Los campos son blancos sobre el panel marino redondeado del prototipo |
| "Te confirmamos por WhatsApp." | n/a | omito. No es lo que hace el flujo |
| Pie "Mariscos La Bahía · Hecho con Menubly" | `nombre`, `config('app.name')` | Nombre, enlaces internos y "Sitio creado con {app}", igual que I |

### Menú (`/{slug}/menu`)

| Bloque del prototipo | Campo / relación de Menuonl | Estado y lo que se construye |
|---|---|---|
| Cabecera marino con esquinas inferiores redondeadas, logo "B" y nombre | `logo`, `nombre` | Igual que el sitio: logo real con tamaño máximo fijo o solo el nombre |
| "Abierto" con punto verde | `$estaAbierto` | "Abierto" / "Cerrado", siempre con texto |
| H1 "Menú" | Texto fijo | "Menú". Nunca "Carta" |
| "Arma tu pedido y lo mandas por WhatsApp." | `eslogan`; `$puedePedir` | Subtítulo = `eslogan` si existe. La indicación sobre el "+" solo aparece cuando se puede pedir (pedidos activos, número válido, abierto, con modalidades) |
| Selector de idioma (no está en el prototipo) | `idiomasDisponibles` | Se agrega como en I, solo con más de un idioma |
| Pastillas de categorías con desplazamiento horizontal | `categorias` visibles con platillos | Anclas `#categoria-N`, sin JavaScript siguen funcionando; la categoría a la vista lleva `aria-current` y relleno coral. Solo con más de una categoría. El desplazamiento horizontal conserva foco visible y llega por teclado |
| Título de cada categoría y filas de platillo | traducción de la categoría y del producto, `imagen`, `precioVigente()`, `FormatoMoneda` | Foto de 76 px (esquinas redondeadas del prototipo) o recuadro neutro, nombre, precio vigente, precio anterior tachado, etiquetas y descripción |
| Variantes | `producto_variantes` | **falta en el prototipo**. Cada variante en su línea, con su precio y su propio "+" (igual que I) |
| Agotado | `disponible`, `mostrar_agotados` | **falta en el prototipo**. Etiqueta "Agotado" con texto y sin botón |
| Botón "+" 44 px | Datos del servidor | Nombre accesible "Agregar {platillo} al pedido"; sin JavaScript queda oculto (`hidden`) y se ofrece el enlace de WhatsApp |
| Tarjeta "Tu pedido" dentro de la página (entrega, líneas, notas, total) | Controlador de pedido compartido | Cambio. Pasa a un **diálogo** `<dialog>` (como I y h), con líneas, nota por línea de 80 caracteres, `<x-sitio.entrega>` (Domicilio / Para recoger), "Notas para la cocina" y total. Las líneas "1 × Aguachile verde / $190" del prototipo son de ejemplo y no se llevan |
| Barra inferior fija "Enviar por WhatsApp · 3 · $470" | Estado del pedido en `localStorage` | Cambio. Barra fija ("Ver pedido", cuenta y total) que abre el diálogo; el envío vive dentro del diálogo. En el prototipo está en `position:absolute` solo por el lienzo; aquí es `fixed` |
| Aviso de cerrado | `pedidosHabilitados()`, `$estaAbierto` | **falta en el prototipo**. "Estamos cerrados ahora…" y sin botones "+" |
| Horario, contacto y pie | `SitioPublicoDatos` | **falta en el prototipo**. La sección Horario/Visítanos y el pie van al final del menú, como en I |
| Nombre de platillos, precios, "Poco picante", etc. | n/a | omito. Son datos de ejemplo |
| `?mesa=` | n/a | Se ignora por completo: sin insignia de mesa (I sí la mostraba), sin llegar al formulario ni al mensaje |

## Textos de la portada

Se derivan de campos existentes, igual que en I y h, sin migración ni campos nuevos:

- h1 = `nombre`. Debajo, `eslogan` y `descripcion`, cada uno omitido si está vacío.
- Pastilla de estado y línea "Hoy: …" = calculados con `horarios` y la zona horaria del restaurante.
- Botones y enlaces = según `pedidosHabilitados()`, `reservas_activas` y los datos de contacto.
- Rótulos fijos de interfaz, ninguno afirma algo del negocio: "Recomendados", "Ver el menú completo", "Galería", "Horario", "Visítanos", "Contacto", "Reserva tu mesa", "Solicitar reserva", "Tu pedido", "Menú".
- Sin testimonios, sin frase de venta, sin "Del mar de Cortés" ni "fresco todos los días".

Pregunta abierta menor (no bloquea): si quieres una segunda línea con "Pedidos a domicilio y para recoger" generada desde `textoDeModalidadesDePedido()` solo cuando el restaurante lo tenga activo. Por defecto NO se incluye.

## Cambios respecto al prototipo, y por qué

1. **Logo real** con tamaño máximo fijo (y el nombre si no hay logo), en lugar de la letra "B": una inicial sería un logo inventado.
2. **H1 = nombre y sin cursiva coral**: no existe el dato "frase de portada" y la cursiva obligaba a una tercera fuente.
3. **"Lo más pedido" a "Recomendados"**: Menuonl no mide ventas.
4. **Estado**: sin "cierra X"; con varios turnos no se sabe qué hora es la de cierre.
5. **Horario**: 7 días sin agrupar y hoy marcado (los rangos agrupados del prototipo no existen en los datos). La sección sigue siendo blanca como en el prototipo, no la banda tinta de I. Si prefieres la banda marino, es un cambio solo de estilos.
6. **Reserva real**: lista de franjas libres, nota, aviso de privacidad y mensajes; botón "Solicitar reserva"; sin "Te confirmamos por WhatsApp".
7. **Pedido en diálogo** con barra fija, en lugar de tarjeta en la página y barra "Enviar por WhatsApp" siempre visible; variantes, agotados, aviso de cerrado y horario/contacto, que el prototipo no tiene.
8. **Contraste**: coral de texto más oscuro, borde de campos más oscuro, hover y estado "Cerrado" definidos (ver paleta).
9. **Marcadores de foto** ("Foto de portada", "Foto del platillo") y cuadrados de relleno de la galería: se omiten. No se copian las fotos del prototipo (no trae ninguna).
10. **Acento**: del restaurante (`color_acento`), no del editor de tres colores del lienzo.
11. **`?mesa=`**: ignorado del todo.

## Pedido por WhatsApp: opción A (confirmada)

Se copia y adapta el patrón de I, sin tocar `pedido/controlador.js` ni `pedido/mensaje.js` (compartidos):

- `resources/js/sitio-mariscos-pedido.js`: `iniciarPedido('mar', 'menuonl_cart_j')`; la clave real en `localStorage` es `menuonl_cart_j_{slug}` (la vista escribe `data-clave="menuonl_cart_j_{slug}"`).
- `resources/js/sitio-mariscos-pedido-mensaje.js`: `export * from './pedido/mensaje.js'`.
- `resources/js/sitio-mariscos.js`: marca la categoría a la vista (`aria-current`) y respeta `prefers-reduced-motion`. Solo se carga con más de una categoría.
- Sin `innerHTML` ni `onclick`; las líneas salen de un `<template>`. `localStorage` guarda solo ids, cantidades y notas.
- `<x-sitio.entrega prefijo="mar" clase-campo="mar-campo" clase-etiqueta="mar-campo__etiqueta" />` para Domicilio / Para recoger. El componente no se toca.
- Se respeta `pedidos_activos`, `whatsapp_pedidos` (8 a 20 dígitos) y "abierto ahora".

## Fuentes

Autoalojadas en `public/fonts/sitio/`, ambas con licencia OFL, con subconjunto latín + acentos del español (mismo conjunto de I: U+0020–007E, á é í ó ú ü ñ y mayúsculas, ¿ ¡ « » ° · ×, comillas, guiones, puntos suspensivos, espacios finos y €), sin hinting, woff2.

| Archivo previsto | Origen | Peso estimado |
|---|---|---|
| `dm-serif-display-es-latin-400.woff2` | `DMSerifDisplay-Regular.ttf` (`google/fonts`, `ofl/dmserifdisplay/`) | 11 848 B |
| `outfit-latin-400.woff2` | Outfit estático 400 (instancia de `Outfit[wght].ttf`, `ofl/outfit/`) | 9 104 B |
| `outfit-latin-600.woff2` | Outfit estático 600 | 9 132 B |
| `OFL-outfit.txt` y `OFL-dm-serif-display-regular.txt` | copiadas sin cambios de `ofl/outfit/` y `ofl/dmserifdisplay/` | texto |

**Peso final medido: 30 084 B** (11 848 + 9 104 + 9 132). Los tres woff2 tienen SHA-256 distintos (comprobado con `sha256sum` y por una prueba). La licencia de DM Serif Display es la del origen (4 605 B); difiere de la de G, por eso lleva nombre propio.

Decisiones y notas:

- **Pesos de Outfit**: el prototipo pide 400, 500, 600 y 700. Se reducen a **400 y 600** (dos archivos); el 500 cae en 400 y el 700 en 600. Menos peso y sin cambio visible apreciable. Si prefieres conservar 500 y 700, sube unos 12 KB.
- **DM Serif Display itálica**: no se necesita (se elimina la cursiva del h1).
- **DM Serif Display ya existe** en el repositorio (`dm-serif-display-latin-400.woff2`, 24 744 B, y `OFL-dm-serif-display.txt`), usado por la plantilla G. Para que J no dependa de un archivo de G (autonomía) se genera un subconjunto propio con otro nombre; G no se toca. La licencia OFL de DM Serif Display ya está en el repositorio; no se modifica y se vuelve a verificar que coincide con la del origen. Se descarga de nuevo del mismo repositorio por si prefieres una copia propia.
- **Herramienta**: `fonttools` (y `brotli` para woff2) no está instalada en este equipo (Python 3.13). Para generarlas hay que instalar ambos con `pip` en el Python del usuario. Es una herramienta de mi entorno, no una dependencia del proyecto (no cambia `composer.json` ni `package.json`). Necesito además descargar los TTF de `google/fonts` una sola vez, solo para construir los archivos; la plantilla en producción no pide nada externo.
- `font-display: swap` y `preload` solo de DM Serif Display (la del h1), como en I.

## Qué se toca fuera de la plantilla (a registrar, no a cambiar otras plantillas)

Lista cerrada, todo es registrar la plantilla nueva o reutilizar lo que I ya dejó:

1. `app/Services/Catalogos.php`: `j` en `plantillasSitio()`.
2. `app/Services/AparienciaSitio.php`: ficha `j` en `fichas()` (nombre "Mariscos", descripción y "para" propuestos más abajo, y 4 colores sacados de la hoja) y `j` en `PLANTILLAS_CON_GALERIA` (`['b', 'i', 'j']`). **No** va en `PLANTILLAS_CON_ESTILO`.
3. `vite.config.js`: `resources/css/sitio/j.css` y los tres scripts de entrada (único archivo de configuración que se toca; es registrar la plantilla).
4. `lang/es/panel.php`: `apariencia.estilo.descripcion` pasa a "…Cafetería, Bar, Cafetería pastel, Pizzería, Japonés y Mariscos traen sus propios colores." El aviso de Fotos (`fotos…descripcion`) se arma desde `PLANTILLAS_CON_GALERIA`, así que dirá "Se ven en los diseños Oscuro elegante, Japonés y Mariscos" sin editar el texto; se verifica en la prueba.
5. Pruebas existentes que listan claves: `SitioCafeteriaTest`, `SitioBarTest`, `SitioPastelTest`, `SitioPizzeriaTest` (más `SitioEditorialOscuroTest` si lista claves; se comprueba), `PanelAparienciaTest`, `TextosPrePilotoTest`, `PlanSitioPublicoTest`, `PanelFotosDelRestauranteTest` (galería: `['b', 'i']` pasa a `['b', 'i', 'j']` y el aviso) y `SitioJaponesTest` si fija la lista de plantillas. Solo se suma `j` a listas, sin cambiar el comportamiento que prueban.

`app/Services/SitioPublicoDatos.php` ya tiene `galeria_fotos` (lo dejó I): **no se toca**.

Sin cambios: `MenuPublicoController`, `Restaurante`, `sitio.js`, `menu.js`, `menuonl.css`, `<x-sitio.entrega>`, `<x-sitio.imagen>`, `pedido/controlador.js`, `pedido/mensaje.js`, `.env`, `public/hot`, `public/build`, backups y las plantillas b a i.

### Ficha propuesta para `AparienciaSitio::fichas()['j']`

- nombre: "Mariscos".
- descripción: "Fondo marino, papel claro y rojo coral, con foto de portada redonda y olas." (en el catálogo: "Fondo marino, papel claro y rojo coral, con olas y foto redonda. Para mariscos y cocina de mar.").
- para: "Marisquerías y cocina de mar".
- colores: `--mar-marino`, `--mar-papel`, `--mar-coral`, y un bloque claro (`--mar-recuadro`), leídos de la hoja y comprobados por una prueba, como en I.

## Archivos que se crearán en fase 2

- Vistas: `resources/views/sitio/j.blade.php`, `resources/views/menu/plantillas/j.blade.php` y `resources/views/components/sitio/mariscos/{layout,cabecera,visita,reservar,pedido,pie}.blade.php`.
- Hoja: `resources/css/sitio/j.css`.
- Scripts: `resources/js/sitio-mariscos.js`, `sitio-mariscos-pedido.js`, `sitio-mariscos-pedido-mensaje.js`.
- Fuentes y licencia nueva en `public/fonts/sitio/`.
- Pruebas: `SitioMariscosTest`, `PedidoWhatsappMariscosTest`, `ReservasMariscosTest`, `tests/Js/pedido-mariscos.test.mjs` y `tests/Js/pedido-mensaje-mariscos.test.mjs`.

## Plan de pruebas (con lo aprendido en I)

- **Hora fija**: toda prueba que dependa de abierto/cerrado, "Hoy:" o "Cerrado ahora" usa `travelTo` con una fecha y hora explícitas en la zona del restaurante, nunca la hora real. Base: `freezeTime()` solo cuando el resultado no depende del día de la semana.
- **Datos**: valores que caben en su columna (`color_acento` de 7 caracteres, `whatsapp_pedidos` de 8 a 20 dígitos; lo largo se prueba en memoria), siempre `$modelo->id` en lugar de ids fijos, sin depender de tablas vacías (MariaDB estricto en CI).
- **`withoutVite()`** en el `setUp` de toda prueba que renderice vistas con `@vite` y no deba depender de `public/build`.
- **Qué cubre `SitioMariscosTest`**: catálogo, ficha, elegible en el panel, sitio y menú completos, abierto y cerrado ahora (con `travelTo`), galería (orden, alt escapado, "Foto de {nombre}", sin fotos), `?mesa=` ignorado (sin insignia), secciones omitidas, recuadro sin foto, logo con y sin imagen, `color_acento` hostil y legible, escape de textos, sin recursos externos ni emojis, enlaces con rutas con nombre, "Carta" ausente, prefijo `mar-` sin choque con `menuonl.css`, colores solo en variables, contraste medido sobre las variables reales (texto 4.5:1, bordes de campo 3:1), fuentes (existen, peso total, licencias OFL con el texto de la SIL Open Font License) y movimiento reducido.
- **b e i no cambiaron**: se renderizan sitio y menú de `b` y de `i` con la misma hora congelada y un restaurante idéntico antes y después de registrar `j`, y se comprueba que (1) siguen en sus mismas vistas (`assertViewIs`), (2) su HTML no contiene `mar-`, `sitio-j`, `data-mar-` ni `menuonl_cart_j`, y (3) el HTML de cada una coincide con una huella (hash) del HTML normalizado tomada con el código actual, para detectar cualquier cambio accidental. Si la huella resultara frágil, se deja solo (1) y (2), igual que `test_las_otras_plantillas_siguen_usando_las_mismas_vistas` de I.
- **.mjs**: nacen con `*.test.mjs` y entran por el mecanismo de `tests/Unit/PedidoWhatsappJsTest.php` (sin carpetas, solo Node 22; sin APIs de Node 24).
- **PHP**: se corre `vendor/bin/pint --dirty --format agent` y solo las pruebas necesarias, no la suite entera.

## Riesgos y cosas a tener presentes

- **Node local es 24.20**, no 22. Las pruebas .mjs deben evitar cualquier API que no exista en 22; no puedo confirmar el comportamiento en 22 desde este equipo.
- `SitioPizzeriaTest::test_el_mantel_...` ya fija la hora (`travelTo('2026-10-22 18:00:00')`, línea 249, desde el commit `57f204f`); la observación anterior quedó resuelta y no se tocó.
- El panel inferior de pedido y el diálogo, la reserva y los 360 px solo se verifican en navegador al terminar la fase 2; las fotos reales y MariaDB solo los confirma el CI.
- Las olas SVG y el panel de reserva redondeado son decoración; deben quedar `aria-hidden` y no afectar el orden de lectura.

## ¿Algo exige migración, cambiar datos o tocar otra plantilla?

No. La clave `j` cabe en `string(1)`, `galeria_fotos` ya existe, el componente de entrega y el controlador del pedido se reutilizan sin cambios, y los únicos archivos fuera de la plantilla son los de registro listados arriba. Por eso no me detuve a preguntar.

Decisiones tuyas que sí cambian lo que construyo (se pueden aprobar juntas o dejar mis valores por defecto):

1. Horario en sección blanca como el prototipo (por defecto) o en banda marino como I.
2. Outfit solo 400 y 600 (por defecto) o también 500 y 700.
3. Instalar `fonttools` y `brotli` con `pip` en tu Python de usuario y descargar los TTF de `google/fonts` para generar las fuentes.
4. Línea opcional de modalidades en la portada (por defecto: no).

## Resultado de las fases 2 y 3

- Construido: vistas, hoja `j.css` (23.11 kB, 4.76 kB gzip), tres scripts, fuentes y licencias, registro en catálogo, ficha, galería, Vite y `lang/es/panel.php`. Pruebas: `SitioMariscosTest` (34), `PedidoWhatsappMariscosTest` (33), `ReservasMariscosTest`, y dos `.mjs` (16 pruebas, Node local 24, sin APIs de Node 24).
- Corrida de las pruebas afectadas (Sitio*, Panel*, Plan*, Textos*, las tres de Mariscos): 373 pruebas, 4 623 aserciones, todas pasan. Pint sin cambios pendientes.
- `vite build` a una carpeta temporal compila `j.css` y los dos scripts; `public/build` no se tocó.
- Contraste, medido sobre las variables reales de `j.css` por la prueba: coral de texto `#b84328` 4.94 sobre papel y 5.42 sobre blanco; borde de campos `#678190` 4.10 sobre blanco y 3.74 sobre papel; blanco sobre coral 4.90; ningún hover pone coral como texto.
- b e i no cambiaron: prueba `test_b_e_i_no_llevan_marcas_de_la_plantilla_j`: render de b e i sin marcas de J (`mar-`, `sitio-j`, `menuonl_cart_j`), sin huellas de archivos.
- **Sin verificar**: aspecto visual en navegador (Playwright no tiene Chrome instalado en este equipo), diálogo del pedido abierto, 360 px, fotos reales y MariaDB (solo el CI).
