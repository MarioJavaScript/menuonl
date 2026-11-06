# Plantilla L "Negro y esmeralda" · Fase 1 (análisis, sin código)

Prototipos leídos: `C:\Users\thexl\Desktop\proyectos\esmeralda\Esmeralda-Sitio.dc.html` (escritorio 1280 px) y `Esmeralda-Menu.dc.html` (celular 390 px). Solo referencia visual: estilos inline y datos de `renderVals()`. El runtime del lienzo (`support.js`, `<x-dc>`, `<sc-for>`, `data-props`) se ignora. Modelo técnico: plantilla K (Cortes premium), sobre J e I.

Estado: solo lectura y este documento. No se creó ni cambió código, no hay migración y no se toca otra plantilla. No se hizo `git add`, commit ni rama. No se tocó `.env`, `public/hot`, `public/build`, backups ni el `.;.swp`.

## Clave y nombres

- Clave **`l`** (`sitio_plantilla` es `string(1)`, sin migración; `l` no aparece en `Catalogos`, `AparienciaSitio`, vistas ni pruebas). Nombre visible **Negro y esmeralda**. Prefijo `esm-`, variables `--esm-*`, clave de pedido `menuonl_cart_l_{slug}`. `a` y `c` siguen retiradas.
- Vistas: `sitio.l` y `menu.plantillas.l`. No entra en `RecomendadosDelSitio::PLANTILLAS_CON_PRINCIPAL`.

## Paleta y contraste del prototipo (fórmula WCAG, medido ahora)

Colores: negro `#0A0A0A`, panel `#111311`, marcador de foto `#161816`/`#1C1F1C`, texto `#EDE8DF`, suave `#B8B2A7`, pie `#8C877E`, verde de texto `#7FCFA8`, verde claro `#9BD9B9`, verde de fondo `#1F6B4A`, filetes `#262626`, borde de campos `#3A3A3A`, guía punteada `#4A4A4A`.

| Par | Contraste | Veredicto |
|---|---|---|
| `#EDE8DF` sobre `#0A0A0A` / `#111311` | 16.22 / 15.30 | bien |
| `#B8B2A7` sobre `#0A0A0A` / `#111311` | 9.39 / 8.86 | bien |
| `#8C877E` (pie) sobre `#0A0A0A` | 5.55 | bien |
| `#7FCFA8` sobre `#0A0A0A` / `#111311` | 10.74 / 10.13 | bien (verde como texto, solo sobre negro) |
| `#9BD9B9` sobre `#0A0A0A` | 12.27 | bien |
| **Blanco sobre `#1F6B4A`** (botón Reservar mesa, barra del pedido, opción elegida) | **6.44** | bien |
| `#EDE8DF` sobre `#1F6B4A` | 5.28 | bien |
| **`#7FCFA8` sobre `#1F6B4A`** | **3.49** | **falla** como texto |
| **`#1F6B4A` como texto sobre `#0A0A0A`** | **3.07** | **falla**: solo fondo, nunca texto |
| **Hover global `a:hover{color:#7FCFA8}` sobre el botón "Cómo llegar" (fondo `#EDE8DF`)** | **1.51** | **falla grave: el botón casi se vacía** |
| **Hover `#7FCFA8` sobre el botón verde "Reservar mesa"** | **3.49** | **falla** (el texto blanco pasa a verde sobre verde) |
| **Borde de campos, "+" y opción sin elegir `#3A3A3A`** sobre `#0A0A0A` / `#111311` | **1.74 / 1.64** | **falla** (pide 3:1) |
| Opción elegida (relleno `#1F6B4A`) contra panel `#111311` | 2.90 | no basta como único indicador; se suma el círculo del radio y un trazo claro |
| Filetes `#262626` y guía punteada `#4A4A4A` | 1.31 / 2.23 | decorativos (`aria-hidden`), no son campos |
| `#0A0A0A` sobre `#EDE8DF` (botón Cómo llegar) | 16.22 | bien |

### Correcciones propuestas (se confirman midiendo las variables reales de `l.css`, con prueba)

- `--esm-borde-campo` nuevo, **`#7A7A7A`**: campos, nota, "+", opciones de entrega y botones con contorno. 4.61 sobre `#0A0A0A` y 4.35 sobre `#111311`. Los filetes siguen en `#262626`.
- Hover, por superficie (nada de regla global):
  - Texto o enlace sobre negro: de `#EDE8DF` pasa a `#7FCFA8` (10.74), con subrayado.
  - Botón claro ("Cómo llegar", fondo `#EDE8DF`): el hover cambia el fondo a `#7FCFA8` y el texto sigue `#0A0A0A` (10.74).
  - Botón verde (fondo `#1F6B4A`, texto blanco): el hover aclara el fondo a un verde medido con texto blanco (propongo `#17583B` más oscuro, 8.42 con blanco, medido) y subraya; el texto no cambia a verde.
  - Botón con contorno: fondo `#EDE8DF` y texto `#0A0A0A` al pasar.
- Foco visible: anillo `#7FCFA8` sobre negro (10.74) y blanco sobre botones verdes (6.44).
- Estado **Cerrado** (el prototipo solo define Abierto): `#E8A0A0` sobre `#0A0A0A` = 9.40, siempre con la palabra y un punto hueco (el abierto es sólido). Error de la reserva: el mismo `#E8A0A0` sobre negro.
- `#1F6B4A` solo como fondo con texto blanco o como adorno (contorno del logo, 3.07 contra negro vale como elemento gráfico). `#7FCFA8` solo sobre negro/panel.
- Acento del restaurante (`color_acento`): como en I, J y K, con `colorAcentoSeguro()`, solo como fondo con texto blanco y solo si llega a 4.5:1; sin acento propio, `#1F6B4A`. El prototipo usaba `{{accent}}` también como contorno del logo; ahí se queda sin texto. Las opciones del `data-props` eran del editor del lienzo.

## Datos: de dónde sale cada bloque

Leyenda: **hay** = campo existente; **derivado** = se calcula; **omito** = el prototipo trae algo que Menuonl no tiene; **falta** = no existe y no se inventa.

### Sitio (`/{slug}`)

| Bloque del prototipo | Campo de Menuonl | Estado y lo que se construye |
|---|---|---|
| Logo: círculo con "V" cursiva | `logo`, `nombre` | Cambio. Logo real con tamaño máximo fijo (`object-fit: contain`); sin logo, solo el nombre. El círculo de acento rodea solo un logo real |
| Nombre "Verdelux" (mayúsculas, espaciado) | `nombre` | hay |
| Navegación: Menú, Galería, Horario, Reservar | `galeria_fotos`, `horarios`, `direccion`, `mapa_url`, `reservas_activas` | "Menú" siempre; "Galería" solo con fotos; "Horario" solo con algún día abierto, dirección o mapa; "Reservar" solo con reservas activas |
| Pastilla "Abierto ahora · cierra 12:00 a. m." | `esta_abierto`, `horarios` | derivado. "Abierto ahora" / "Cerrado ahora" (palabra + punto distinto), solo si hay algún día abierto. Sin "cierra X" |
| H1 "Una noche *para recordar*" (92 px, cursiva verde) | `nombre` | **omito**: frase de venta que Menuonl no conoce. El h1 es el nombre, tamaño con `clamp()` y `overflow-wrap` (92 px fijos desbordan a 360 px). Sin cursiva |
| Párrafo "Cocina de autor, coctelería y una carta de vinos…" | `eslogan`, `descripcion` | derivado de campos; cada uno se omite si está vacío |
| (no está) "Hoy: horario" | `horarios`, zona horaria | Se agrega como en I, J y K; se omite si hoy está cerrado |
| Botón "Reservar mesa" | `reservas_activas` | Solo con reservas activas |
| Botón "Ver menú" | `menu_url`, `pedidosHabilitados()` | "Ver menú y pedir" con pedidos habilitados; si no, "Ver menú" |
| Portada 16:7 (marcador "Foto de portada") | `portada` | Foto real recortada 16:7. Sin portada se omite el bloque (sin marcador). Alt "Foto de {nombre}" |
| Etiqueta "Selección del chef" sobre "Recomendados" | n/a | **omito**: afirma algo que el restaurante no declaró. Queda solo "Recomendados" |
| Lista de 6 destacados (nombre, guía punteada, precio verde, descripción) | `destacados` (hasta 6, sin agotados) | Mismo diseño de filas, a dos columnas con `minmax(min(100%, 420px), 1fr)` para que no desborde. Precio vigente, anterior tachado y etiquetas. **Solo si hay destacados**; si no, se omite la sección |
| "Ver el menú completo" | `menu_url` | hay (dentro de la sección de destacados) |
| Galería: 3 cuadros 3:4 de color | `galeria_fotos` (`SitioPublicoDatos`) | Cambio. Fotos reales en el orden existente, **aspect-ratio 4 / 3 y `object-fit: cover`**, rejilla `auto-fill` con ancho máximo por tarjeta (como `.cor-galeria`), para que fotos verticales y pocas fotos no se estiren. Alt = descripción escapada; vacía: "Foto de {nombre}". Se omite sin fotos |
| Horario: 3 filas agrupadas ("Martes a jueves"…) | `horarios` | Cambio. Los 7 días, lunes primero, sin agrupar, hoy marcado; el día cerrado dice "Cerrado" |
| Visítanos: dirección en dos líneas | `direccion` | hay; se omite si falta. Sin dirección el título pasa a "Contacto" |
| Botones Cómo llegar, WhatsApp, Llamar | `mapa_url`, `whatsapp_digitos`, `telefono_enlace` | Cada uno si tiene dato; se suman Instagram y Facebook si existen. **Mismo alto y alineación aunque caigan a una segunda fila** (`min-height` común, `box-sizing`, borde de 1 px en todos, `align-items`). Sin horario ni contacto, la sección no se dibuja |
| Reserva: fecha, hora (`type="time"`), personas, nombre, teléfono, "Enviar reserva" | Formulario real (`sitio.js`, `data-url`, `data-disp`) | Cambio. Hora = franjas libres que llena `sitio.js`; se suman nota opcional, campo trampa, aviso de privacidad y mensaje de estado. Botón "Solicitar reserva" |
| "Te confirmamos por WhatsApp." | n/a | **omito** |
| Pie: nombre + "Hecho con Menubly" | `nombre`, `config('app.name')` | Nombre, enlaces internos y "Sitio creado con {app}" |

### Menú (`/{slug}/menu`)

| Bloque del prototipo | Campo de Menuonl | Estado y lo que se construye |
|---|---|---|
| Cabecera: logo "V" + nombre | `logo`, `nombre` | Logo real con tamaño máximo fijo o solo el nombre |
| "Abierto ahora" con punto | `$estaAbierto` | "Abierto" / "Cerrado" siempre con palabra |
| H1 "Menú" (44 px) | texto fijo | "Menú". Nunca "Carta" |
| Selector de idioma (no está) | `idiomasDisponibles` | Se agrega solo con más de un idioma |
| Navegación de categorías (Entradas, Fuertes, Postres, Coctelería) | `categorias` visibles con platillos | Las del restaurante, no las del prototipo. Anclas `#categoria-N`, `aria-current` y subrayado verde en la actual. Solo con más de una categoría |
| Título de categoría (Bodoni verde cursiva 28 px) y filas | traducciones, `precioVigente()`, `FormatoMoneda` | Mismo diseño. Sin cursiva (ver fuentes). Precio anterior tachado y etiquetas |
| Foto de platillo | `imagen` | **El prototipo no trae.** Miniatura solo si el platillo tiene foto; sin foto, nada y sin recuadro vacío |
| Variantes | `producto_variantes` | **falta en el prototipo**: cada variante en su línea con precio y "+" |
| Agotado | `disponible`, `mostrar_agotados` | **falta**: etiqueta "Agotado" con texto y sin botón |
| Botón "+" 44 px | datos del servidor | Nombre accesible "Agregar {platillo} al pedido"; borde `--esm-borde-campo`; sin JavaScript queda oculto y se ofrece el enlace de WhatsApp |
| Tarjeta "Tu pedido" en la página (¿Cómo lo quieres?, líneas, Notas, Total) | controlador de pedido compartido | Cambio. Pasa a `<dialog>` con líneas, nota por línea de 80 caracteres, `<x-sitio.entrega>` (Domicilio / Para recoger), "Notas para la cocina" y total. Las líneas de ejemplo y "Término medio" no se llevan |
| Barra inferior "Enviar por WhatsApp · 2 · $800" (`absolute`) | estado en `localStorage` | Barra `fixed` "Ver pedido" con cuenta y total que abre el diálogo; el envío va dentro. El `absolute` era del lienzo |
| Aviso de cerrado | `pedidosHabilitados()`, `$estaAbierto` | **falta**: "Estamos cerrados ahora…" y sin "+" |
| Horario, contacto y pie | `SitioPublicoDatos` | **falta**; van al final como en I a K |
| Ancho 390 px y alto 2300 px | n/a | Cambio: fluido, contenedor centrado (máx. ~720 px), alto automático |
| `?mesa=` | n/a | Se ignora por completo (sin insignia ni formulario) |

## Textos de la portada

Todo sale de campos existentes, sin migración:

- h1 = `nombre`; debajo `eslogan` y `descripcion`, cada uno omitido si está vacío. **Sin subtítulo bajo el nombre.**
- Pastilla y "Hoy: …" = `horarios` + zona horaria.
- Botones según `pedidosHabilitados()`, `reservas_activas` y contacto.
- Rótulos fijos que no afirman nada: "Recomendados", "Ver el menú completo", "Galería", "Horario", "Visítanos", "Contacto", "Reserva tu mesa", "Solicitar reserva", "Tu pedido", "Menú".
- Sin "Selección del chef", testimonios, "Cocina de autor", "te confirmamos por WhatsApp" ni "cierra X".

## Cambios respecto al prototipo, y por qué

1. Logo real o nombre; no la "V" inventada.
2. H1 = nombre, sin frase ni cursiva (no existe el dato).
3. Sin "Selección del chef" ni la frase de portada: textos de venta sin dato.
4. Recomendados solo con destacados.
5. Galería con fotos reales 4:3 (el prototipo usaba 3:4 y cuadros de color) y rejilla con ancho máximo.
6. Horario de 7 días sin agrupar.
7. Reserva real; sin "Te confirmamos por WhatsApp".
8. Pedido en diálogo con barra fija; variantes, agotados, aviso de cerrado, horario/contacto que el prototipo no tiene.
9. Contraste: bordes de campo, hovers (el global dejaba el botón claro casi vacío), Cerrado y reglas de uso del verde.
10. Responsive: h1 con `clamp()`, columnas con `min(100%, …)`, menú fluido; el prototipo de sitio no cabe a 360 px (92 px de h1, columnas de 420 px, 5 botones de navegación con 32 px de separación).
11. Marcadores de foto omitidos; no se copian fotos.
12. Acento del restaurante, no del editor del lienzo.
13. `?mesa=` ignorado.
14. Botones del pie con alto y alineación fijos en cualquier fila.

## Pedido por WhatsApp: opción A (confirmada)

Copia y adaptación de `sitio-cortes-pedido*.js`, sin tocar `pedido/controlador.js` ni `pedido/mensaje.js`:

- `resources/js/sitio-esmeralda-pedido.js`: `iniciarPedido('esm', 'menuonl_cart_l')`; clave real `menuonl_cart_l_{slug}` (la vista escribe `data-clave`).
- `resources/js/sitio-esmeralda-pedido-mensaje.js`: `export * from './pedido/mensaje.js'`.
- `resources/js/sitio-esmeralda.js`: categoría a la vista (`aria-current`) y `prefers-reduced-motion`.
- Sin `innerHTML` ni `onclick`; líneas desde `<template>`; `localStorage` solo con ids, cantidades y notas.
- `<x-sitio.entrega prefijo="esm" clase-campo="esm-campo" clase-etiqueta="esm-campo__etiqueta" />`, sin tocar el componente.
- Respeta `pedidos_activos`, `whatsapp_pedidos` (8 a 20 dígitos) y "abierto ahora".

## Fuentes

Autoalojadas en `public/fonts/sitio/`, ambas OFL, subconjunto latín + español con `fonttools` 4.68 (mismo conjunto de I a K, sin hinting, woff2). Los TTF salen de `google/fonts` (`ofl/bodonimoda/`, `ofl/jost/`), con su `OFL.txt` copiada sin cambios.

**Hallazgo: las dos son variables.** `BodoniModa[opsz,wght].ttf` (ejes óptico 6–96 y peso 400–900) y `Jost[wght].ttf`. Se fijan instancias estáticas con `fontTools.varLib.instancer`.

**Bodoni Moda y el eje óptico.** Un solo `opsz` estático no sirve a 92 px y a 18 px a la vez: a opsz alto los trazos finos desaparecen en un precio de 18 px sobre negro; a opsz bajo el h1 pierde elegancia. Propuesta: **fijar `opsz = 28` para todo** (títulos de 44–56 px aguantan, precios y nombres de 18–24 px siguen legibles; el h1 de 92 px del prototipo baja a un `clamp()` máximo ~64 px donde ese opsz luce bien). Lo confirmo mirando la instancia renderizada antes de cerrar; si a 18 px los finos se pierden, sube a `opsz = 18`. Se informa el valor final.

**Pesos que usa el prototipo.** Bodoni: 500 (h1, h2, nombres, logo), precios sin peso declarado (= 400), cursiva 500 (h1 "para recordar", títulos de categoría, "V"). El 700 se carga pero no se usa. Jost: 300 (descripciones), 400 (cuerpo y navegación), 500 (botones, `dt`, total); el 600 se carga pero no se usa.

| Archivo previsto | Contenido | Nota |
|---|---|---|
| `bodoni-moda-esmeralda-latin-500.woff2` | Bodoni Moda 500 normal, `opsz` fijo | también los precios (el 400 con finos a 18 px pierde contraste visual sobre negro) |
| `jost-esmeralda-latin-400.woff2` | Jost 400 | cuerpo; absorbe el 300 (el 300 a 13 px sobre negro es delgado en pantallas bajas) |
| `jost-esmeralda-latin-500.woff2` | Jost 500 | botones, `dt`, total |
| `OFL-bodoni-moda.txt`, `OFL-jost.txt` | licencias sin cambios | |

Peso estimado: unos 25 a 35 KB en total (Bodoni suele pesar más por sus contornos). **Peso final y `sha256sum` de cada woff2 los reporto en la fase 2** (deben ser distintos entre sí y distintos de los existentes, nombre propio, sin depender de archivos de otras plantillas). Si el 300 de Jost resulta imprescindible, suma un cuarto archivo de ~9 KB.

Sin cursiva: omitir el italic evita un cuarto archivo y la sustituye el verde `#7FCFA8` y el espaciado en los títulos de categoría. Si prefieres conservar la cursiva (un archivo más, ~12 KB), dímelo.

`font-display: swap`; `preload` solo del Bodoni 500.

## Qué se toca fuera de la plantilla (a registrar; lo reporto)

1. `app/Services/Catalogos.php`: `l` en `plantillasSitio()`.
2. `app/Services/AparienciaSitio.php`: ficha `l` en `fichas()` y `l` en `PLANTILLAS_CON_GALERIA` (`['b','i','j','k','l']`). **No** va en `PLANTILLAS_CON_ESTILO`.
3. `vite.config.js`: `l.css` y los scripts de entrada.
4. `lang/es/panel.php`: `estilo.descripcion` suma "Negro y esmeralda" a la lista; el aviso de Fotos se arma desde `PLANTILLAS_CON_GALERIA`; ajusto `PanelFotosDelRestauranteTest`.
5. Pruebas existentes que solo suman `l` a listas de claves: todos los `Sitio*Test`, `PanelAparienciaTest`, `TextosPrePilotoTest`, `PlanSitioPublicoTest`, `PanelFotosDelRestauranteTest`, y los `.mjs` nuevos por `PedidoWhatsappJsTest`.

Sin cambios: `SitioPublicoDatos` (ya trae `galeria_fotos`), `MenuPublicoController`, `Restaurante`, `sitio.js`, `menu.js`, `menuonl.css`, `<x-sitio.entrega>`, `pedido/controlador.js`, `pedido/mensaje.js`, `.env`, `public/hot`, `public/build`, backups y las plantillas b a k.

Nota para el cierre: Pint puede cambiar los finales de línea de `AparienciaSitio.php`, `Catalogos.php` y `lang/es/panel.php`; se restauran al original para que el diff no salga reescrito.

### Ficha propuesta para `fichas()['l']`

- nombre: "Negro y esmeralda".
- descripción: "Fondo negro con acento verde esmeralda y tipografía de alto contraste. Menú en lista." Catálogo: "Fondo negro con acento verde esmeralda. Para restaurantes de cocina de autor, coctelería y cenas."
- para: "Cocina de autor y cenas".
- colores leídos de la hoja: fondo `#0a0a0a`, texto `#ede8df`, acento `#1f6b4a`, bloque `#7fcfa8`.

## Archivos que se crearán en fase 2

- Vistas: `resources/views/sitio/l.blade.php`, `resources/views/menu/plantillas/l.blade.php`, `resources/views/components/sitio/esmeralda/{layout,cabecera,visita,reservar,pedido,pie}.blade.php`.
- Hoja `resources/css/sitio/l.css`; scripts `sitio-esmeralda.js`, `sitio-esmeralda-pedido.js`, `sitio-esmeralda-pedido-mensaje.js`.
- Fuentes y licencias en `public/fonts/sitio/`.
- Pruebas: `SitioEsmeraldaTest`, `PedidoWhatsappEsmeraldaTest`, `ReservasEsmeraldaTest`, `tests/Js/pedido-esmeralda.test.mjs`, `tests/Js/pedido-mensaje-esmeralda.test.mjs`.

## Plan de pruebas

- Toda prueba que dependa de abierto/cerrado, "Hoy:" o "Cerrado ahora" fija la hora con `travelTo`.
- Valores que caben en su columna (`color_acento` 7 caracteres; `whatsapp_pedidos` 8 a 20 dígitos; lo largo en memoria), `$modelo->id`, sin tablas vacías. `withoutVite()` en el `setUp` si se lee `public/build`.
- `SitioEsmeraldaTest`: catálogo, ficha, elegible en el panel, sitio y menú completos, abierto/cerrado, galería (orden, alt escapado, "Foto de {nombre}", sin fotos), `?mesa=` ignorado, secciones omitidas, logo con y sin imagen, `color_acento` hostil, escape, sin recursos externos ni emojis, enlaces con `route()`, "Carta" ausente, prefijo `esm-` sin choque con `menuonl.css`, colores solo en variables, fuentes (existen, OFL, sha256 distintos).
- Contraste medido sobre las variables reales de `l.css`: texto 4.5:1; bordes de campo y "+" 3:1; blanco sobre `#1F6B4A`; el verde solo como texto sobre negro/panel; ningún hover deja el texto igual al fondo; Cerrado con palabra.
- **Regla de proporción de la galería**: la prueba lee `l.css` y exige `aspect-ratio: 4 / 3`, `object-fit: cover` y `auto-fill` con ancho máximo en `.esm-galeria`.
- **b, i, j y k no llevan marcas de l**: render de sitio y menú sin `esm-`, `sitio-l`, `data-esm-`, `menuonl_cart_l`, y siguen en sus vistas. Sin huellas SHA de archivos.
- Suma de `l` a las pruebas existentes (arriba); `.mjs` por `PedidoWhatsappJsTest` (Node 22, sin carpetas, sin APIs de Node 24).
- Pint con `--dirty`, restaurando finales de línea; después la suite.

## Riesgos

- Node local es 26.12, no 22; el comportamiento en 22 solo lo confirma el CI.
- Navegador real a 360/390/768/1024/1360 px, diálogo del pedido, fotos reales y MariaDB: solo se verifican al terminar la fase 2 o en el CI.
- Bodoni Moda estática pesa más que los otros serif; el total de fuentes se reporta con `sha256sum`.

## ¿Algo exige migración, cambiar datos o tocar otra plantilla?

No. `l` cabe en `string(1)`, `galeria_fotos` ya existe, `<x-sitio.entrega>` y el controlador de pedido se reutilizan; lo único fuera de la plantilla es el registro y las listas de pruebas. No me detuve a preguntar.

## Decisiones tuyas (si no dices nada, aplico lo que va primero)

1. **Bodoni Moda estática con `opsz = 28`, peso 500, sin cursiva, precios en 500** (por defecto). Alternativa: conservar la cursiva (+1 archivo, ~12 KB).
2. **Jost 400 y 500** (el 300 pasa a 400) (por defecto). Alternativa: conservar 300 (+1 archivo).
3. **Miniatura de platillo** solo si hay foto, de 64 px (por defecto).
4. **Hover del botón verde** con fondo más oscuro `#17583B` y texto blanco (por defecto).
5. **Borde de campos** `#7A7A7A` (por defecto).

## Resultado de las fases 2 y 3

- Construido: vistas `sitio/l` y `menu/plantillas/l`, seis componentes en `components/sitio/esmeralda/`, hoja `l.css`, tres scripts (`sitio-esmeralda*.js`, pedido opción A con `menuonl_cart_l_{slug}`), fuentes y licencias, registro en `Catalogos`, ficha y galería en `AparienciaSitio`, `vite.config.js` y `lang/es/panel.php`.
- Fuentes (subconjunto latín + español, sin hinting): `bodoni-moda-esmeralda-latin-500.woff2` 10 284 B (instancia estática wght 500, opsz 28, sin `fvar`, `usWeightClass` 500), `jost-esmeralda-latin-400.woff2` 6 232 B, `jost-esmeralda-latin-500.woff2` 6 676 B. Peso final: 23 192 B. Los tres sha256 son distintos entre sí y de todos los woff2 existentes.
- Contrastes medidos por prueba sobre las variables reales de `l.css`: texto 4.5:1 en todos los pares usados; blanco sobre `#1f6b4a` 6.44 y sobre el hover `#17583b` 8.42; bordes `#7a7a7a` 4.61 sobre negro y 4.35 sobre panel; `#1f6b4a` y `#7fcfa8` sobre el otro quedan por debajo de 4.5 y no se usan así; los hover con par de color y fondo se miden uno a uno.
- Sin verificar: revisión visual en navegador a 360/390/768/1024/1360 px y el diálogo del pedido (no hay Chrome para Playwright en este equipo); MariaDB.
