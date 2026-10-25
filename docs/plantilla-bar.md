# Plantilla F "Bar": análisis (Fase 1)

Prototipo aprobado: Ocaso, coctelería (carpeta `bar`, ver "Estado de las dependencias"). Este documento resume el análisis previo a construir la plantilla `f`. No se escribió código ni se tocó ningún archivo salvo este.

## Estado de las dependencias

- **Plantilla e**: no está en la rama `plantilla-bar` (parte de `main`, que solo tiene a a d). Se leyó con `git show plantilla-cafeteria:...` (commits `f570c67`, `604091c`, `ad162e2`). Para construir la Fase 2 hace falta que e esté en la rama: `MenuPublicoController` (elige `menu.plantillas.{clave}`), `Restaurante::whatsappPedidosDigitos()`, el selector `sitio_plantilla` de `EditRestauranteProfile`, `x-sitio.imagen` ya existe en main. Eso lo fusiona el dueño del proyecto (yo no hago merge ni commit).
- **Prototipo**: `C:\proyectos\bar` no existe en este equipo. Se leyó `C:\Users\thexl\Desktop\proyectos\bar` (README, REPORTE, CREDITOS, `js/`, `data/`, `dist/index.html`). El prototipo no trae carpeta `src/`; las fuentes están en `js/`, `css/`, `data/`. Si esa no es la carpeta aprobada, hay que avisar.

## Cómo está hoy (resumen de lo que e dejó resuelto)

- **Sitio** (`/{slug}`): `SitioPublicoController` pide los datos a `SitioPublicoDatos::para()` y renderiza `sitio.{plantilla}`; la clave sale de `restaurantes.sitio_plantilla` y cae a `a` si no está en `Catalogos::plantillasSitio()`.
- **Menú** (`/{slug}/menu`): `MenuPublicoController` elige `menu.plantillas.{clave}` si la vista existe y, si no, `menu.publico`. Para a a d no existe, así que no cambian. Para f bastará con añadir `menu/plantillas/f.blade.php`; el controlador no se toca.
- **Panel**: el selector se dibuja en `resources/js/panel.js` con la lista de `Catalogos::plantillasSitio()` que entrega `AjustesController`; "Mi restaurante" (Filament) usa la misma lista. Registrar `f` basta para que sea seleccionable en ambos.
- **Acento**: `Restaurante::colorAcentoSeguro()` (hex de 6 dígitos o `null`) y `colorAcentoTexto()`. e los valida con contraste 4.5:1 en su layout.
- **Pedido** (e): botones con `data-*` escritos por el servidor, cajón `<dialog>`, `localStorage` con ids, cantidades y notas, `textContent` y nodos DOM, sin `innerHTML` ni `onclick`. Puede pedir solo si `pedidos_activos && estaAbierto && whatsappPedidosDigitos() !== null`. Mesa editable con `?mesa=` (alfanumérico, hasta 20) y "Para llevar" si queda vacía, nota para la cocina, enlace recortado a 2000 caracteres.
- **Reservas** (e): mismo marcado funcional que `sitio/partes/reservar.blade.php` (ids `form-reserva`, `rs-*`, `reserva-msg`, campos y `data-url`/`data-disp`), reutilizando `resources/js/sitio.js` y `ReservaPublicaController` (validación, campo trampa, `ts`, límites de tasa, cupo). Solo cambian las clases.

## Datos: de dónde sale cada bloque

| Bloque del prototipo | Campo / relación de Menubly | Estado |
|---|---|---|
| Nombre (encabezado, portada, pie) | `restaurantes.nombre` | Listo |
| Logo junto al nombre | `restaurantes.logo` | Listo; sin logo se omite |
| Eslogan | `restaurantes.eslogan` | Listo; se omite si está vacío |
| Descripción de la portada | `restaurantes.descripcion` | Listo; se omite si está vacía |
| Foto de portada con velo oscuro | `restaurantes.portada` | Listo; sin foto, portada sin imagen (fondo plano de la hoja) |
| Nombre gigante que se enciende como neón | El mismo nombre; efecto solo en CSS, solo con movimiento permitido | Sin campo nuevo |
| Botón "Ver la carta" | `route('menu.publico')`, texto "Ver el menú" | Listo (el prototipo dice "carta"; aquí siempre "Menú") |
| Botón "Reservar mesa" | `reservas_activas` + `#reservar` | Listo; solo con reservas activas |
| "Cóctel de la casa" (platillo destacado) | Primer elemento de `destacados` de `SitioPublicoDatos` (productos marcados destacados; si no hay, los primeros visibles) | Parcial: ver nota de la etiqueta abajo |
| Vista previa de la carta (6 platillos) | `destacados` (hasta 6) | Parcial: no incluye variantes ni orden por categoría; ver riesgos |
| Horario con el día de hoy marcado | `horarios` (7 líneas, lunes primero) y el día actual en la zona del restaurante | Listo; se omite si todos los días están cerrados |
| Reservas: nombre, teléfono, personas, fecha, hora, nota | Formulario reutilizado de las reservas públicas | Listo; la hora es una lista de franjas libres del servidor (no un campo libre) |
| Reservas: validación en el navegador y confirmación simulada | Servidor (`ReservaPublicaController`) | Se sustituye por la real; el aviso "solicitud de muestra" se elimina |
| Contacto: Llamar, WhatsApp, Instagram, Facebook | `contactos` (teléfono, whatsapp, instagram, facebook) vía `SitioPublicoDatos` | Listo; cada botón se omite si falta su dato |
| Ubicación: dirección y "Cómo llegar" | `contactos` tipo `direccion` y `mapa_url` (coordenadas o dirección) | Listo; se omite si faltan ambos |
| Pie: nombre, dirección, redes | Los mismos datos | Listo |
| Menú: categorías con filtro | `categorias` visibles con traducción | Listo; el filtro "Todo" se agrega por código y solo con más de una categoría |
| Fila de menú: nombre, línea punteada, precio | Traducción del producto + `FormatoMoneda` | Listo |
| Descripción del platillo | `producto_traducciones.descripcion` | Listo; se omite si está vacía |
| Foto del platillo (miniatura de 88 px) | `productos.imagen` | Listo; sin foto, la fila no lleva miniatura |
| Precio anterior tachado | `precio` cuando `precioVigente()` es menor | Listo |
| "desde" cuando hay variantes con distinto precio | `producto_variantes` | Cambia: cada variante se lista con su precio y su botón (como e), sin "desde" |
| Etiquetas | `etiquetas` vía `Catalogos::nombreEtiqueta()` | Listo |
| Agotado (texto, no solo color) | `disponible` y `mostrar_agotados` | Listo |
| Mensaje "la carta se está preparando" | Sin platillos visibles | Listo, como texto de estado de la plantilla ("Este menú se está preparando.") |
| Insignia "Mesa N" en el encabezado | `?mesa=` validada en `MenuPublicoController` | Listo en el menú; no se muestra en el sitio, que no recibe la mesa |
| Pedido: barra "Ver pedido", cajón, cantidades, nota por línea, enviar por WhatsApp | Pedido de e (ver "Script del pedido") | Listo; precios y nombres salen del servidor |
| Selector de idioma | `restaurante_idiomas` | No existe en el prototipo; se añade un selector mínimo si hay más de uno, como e |
| Acento (`color_acento`) | `colorAcentoSeguro()` / `colorAcentoTexto()` | Ver "Acento" |
| `foto_alt` por platillo y `foto_portada_alt` | No existen en Menubly | Se omite: el texto alternativo es el nombre del platillo y "Portada de {nombre}" |
| Aviso "es una solicitud de muestra" y datos inventados de Ocaso | Prototipo | No se llevan |
| Fotos de Ocaso (`assets/img/*.webp`) | Prototipo | No se copian; se usan las del restaurante y, sin foto, un recuadro neutro |

No hay testimonios, datos nutricionales ni otros bloques que Menubly no guarde. El único texto del prototipo que no tiene dato propio es el de las etiquetas fijas de sección (Horario, Reservar, Contacto, Ubicación, "Ver el menú"), que son rótulos de interfaz, como en e.

## Textos de la portada

Opciones:

1. **Derivarlos de los campos que ya existen (recomendada).** El prototipo solo usa nombre, eslogan y descripción en la portada, y los tres existen. El nombre va como título gigante, el eslogan y la descripción debajo, y cada uno se omite si está vacío. No hay palabras partidas ni cursivas inventadas (a diferencia de e). Sin migración ni formulario nuevo.
2. **Campos nuevos** (por ejemplo, un texto corto de portada). No hace falta para Bar y exigiría migración y cambios en el panel.

Recomiendo la 1.

Etiqueta del platillo destacado: el prototipo dice "Cóctel de la casa", que es vocabulario de coctelería y falso en otro tipo de restaurante. Propongo la misma regla que e: "Nuevo" si el producto lleva la etiqueta `nuevo` y "Recomendado" en otro caso. No se inventa texto y funciona para cualquier restaurante.

## Menú por plantilla

| Plantilla | Vista del menú | Diseño |
|---|---|---|
| a, b, c, d | `menu.publico` | `menuonl.css` + estilo (tema) del restaurante, sin cambios |
| e | `menu.plantillas.e` | Propio (Cafetería), sin cambios |
| f | `menu.plantillas.f` (nueva) | Propio (Bar): hoja `resources/css/sitio/f.css`, sin `menuonl.css` ni Tailwind ni estilo del restaurante |

## Diseño y decisiones propias

- **Hoja `f.css`**: variables `--bar-*` en `:root` (los colores del prototipo: fondo muy oscuro, tarjeta, crema, magenta de neón; ver el prototipo para los valores), clases con prefijo `bar-` (más `foto`, `foto-vacia`, `sitio`, `sitio-f` heredadas de `x-sitio.imagen` y del `body`, como en e). Sin hexadecimales ni `rgb()` fuera de `:root`, también en las vistas.
- **Tipografías**: Bebas Neue (títulos y precios) y Space Grotesk 400/500/700 (texto), subconjunto latino, de los paquetes del prototipo, en `public/fonts/sitio/` con `OFL-bebas-neue.txt` y `OFL-space-grotesk.txt` (las licencias OFL del propio prototipo). `@font-face` en `f.css` y `preload` de la fuente de títulos. No hay fuentes ni recursos externos.
- **Movimiento**: el encendido del neón y `@view-transition { navigation: auto; }` van dentro de `@media (prefers-reduced-motion: no-preference)`; sin movimiento todo queda estático.
- **Estructura**: `components/sitio/bar/{layout,cabecera,contacto,reservar,pedido,pie}.blade.php`, `sitio/f.blade.php` y `menu/plantillas/f.blade.php`, igual que e con la carpeta `bar`.
- **Menú sin JavaScript**: todo se renderiza en el servidor (a diferencia del prototipo, que pinta con JS); el filtro por categoría es mejora progresiva y el botón "Pedir por WhatsApp" sin pedido armado queda como respaldo, igual que e.
- **Cajón del pedido** (el prototipo solo lo muestra en una captura): barra inferior fija "Ver pedido (n) · total" y `<dialog>` con líneas (cantidad con más y menos, nota por línea), campos Mesa y "Nota para la cocina", total informativo, "Enviar por WhatsApp", "Vaciar pedido" con confirmación y el aviso "Abrimos WhatsApp... ¿ya lo mandaste?" de e. Visualmente: panel oscuro con borde de neón, botones del color base.
- **Reservas**: el prototipo usa campos libres; aquí se conserva el formulario real (la hora depende de la disponibilidad), con la rejilla de dos columnas del prototipo, errores en el color de error de la hoja (contraste ≥ 4.5:1 sobre la tarjeta) y los elementos que crea `sitio.js` (`.error`, enlace `btn btn-alt`) estilados en `f.css`.
- **Acento**: igual que e. El acento del restaurante solo cambia el fondo de botones y las marcas decorativas, con el texto de `colorAcentoTexto()`, y solo si ese par llega a 4.5:1; los textos de color sobre superficies oscuras usan siempre el magenta base de la hoja, así el contraste no depende de un color ajeno. (El prototipo también exigía 4.5:1 del acento contra la tarjeta; eso no se puede comprobar en PHP sin copiar un hexadecimal a la vista, por eso el acento no se usa como color de texto.)
- **"Menú"** en toda la interfaz; nunca "Carta".

## Script del pedido: dos opciones

**A. Copiar el de e y adaptarlo (recomendada).** Dos archivos nuevos, `resources/js/sitio-bar-pedido.js` y `sitio-bar-pedido-mensaje.js`, con atributos `data-bar-*`, clave `menuonl_cart_f_{slug}` y las mismas reglas de e. Se copian también las pruebas de Node a `tests/Js/`. Riesgo: duplicación. El formato del mensaje pasaría a vivir en tres sitios (`menu.publico`, e y f) y hay que mantenerlos iguales a mano; la mitigación es una prueba de Node que fija el formato de f y una comparación con el de e. e, a, b, c y d no cambian: ni HTML, ni JS, ni comportamiento.

**B. Extraer un módulo compartido que usen e y f.** Mover las reglas puras (y, si se quiere, la lógica del carrito parametrizada por prefijo) a un módulo común e importarlo desde e y f. Riesgo: tocar `sitio-cafeteria-pedido*.js` cambia el archivo que sirve e (el bundle se vuelve a generar con otro hash) y obliga a parametrizar los atributos `data-cal-*`; la salida de e tendría que demostrarse idéntica, con el riesgo de una regresión en una plantilla ya aprobada. Si se elige B, hay que parar y confirmar antes, porque cambia archivos de e.

Variante intermedia de A, sin tocar e: que `sitio-bar-pedido.js` importe tal cual `sitio-cafeteria-pedido-mensaje.js` (reglas puras, sin DOM) y solo copie el script de DOM. Evita duplicar el formato del mensaje, pero ata f a un archivo con nombre de e; si e se renombra o se borra, f se rompe. No la recomiendo, pero está disponible.

Elección propuesta: **A**, porque no toca la salida de e.

## Qué se toca fuera de la plantilla nueva (estrictamente necesario)

1. `app/Services/Catalogos.php`: registrar `f` en `plantillasSitio()`.
2. `vite.config.js`: registrar `f.css` y los scripts de f.
3. Pruebas existentes que cuentan o enumeran las plantillas y fallarían al registrar `f`: `tests/Feature/PanelAjustesTest.php` (el conteo pasa de 5 a 6), `tests/Feature/SitioEditorialOscuroTest.php` y `tests/Feature/SitioCafeteriaTest.php` (la lista `['a'..'e']` pasa a incluir `f`). Solo cambian esas expectativas; no cambia el comportamiento que prueban.

Propuesta aparte, que no es necesaria para registrar la plantilla y por eso no la hago sin tu visto bueno: `EditRestauranteProfile` dice "La plantilla Cafetería trae sus propios colores y tipografías y no usa el estilo visual de arriba". Con Bar ese texto queda incompleto. Sugiero cambiarlo a "Las plantillas Cafetería y Bar traen sus propios colores y tipografías y no usan el estilo visual de arriba." Es solo texto del panel. Si no lo apruebas, queda como está.

No se toca `frontend/`, `publico.blade.php`, `dish-card.blade.php`, `menu.js`, `sitio.js`, `MenuPublicoController`, `SitioPublicoDatos`, `Restaurante` ni las plantillas a a e.

## Migraciones y datos existentes

Ninguna migración, ningún dato existente cambia y ninguna otra plantilla cambia de comportamiento. Con la salvedad de fusionar e en la rama, la Fase 2 no necesita nada de eso.

## Riesgos y puntos a verificar en la Fase 2

- **Vista previa en la portada**: `destacados` no expone variantes ni el orden por categoría que usa el prototipo. Un producto con variantes mostraría su precio base; hay que comprobar qué precio tiene en ese caso y, si no es representativo, no mostrar precio en esa fila.
- **Día de hoy en el horario**: se calcula en el servidor con la zona del restaurante; la página del sitio se cachea 30 segundos (`public, max-age=30`), un desfase irrelevante salvo a medianoche.
- **Duplicación del formato del mensaje** (opción A): mitigada con pruebas, pero depende de que quien cambie `menu.publico` actualice e y f.
- **Contraste sobre la foto de portada**: el prototipo reprodujo un velo para llegar a 6.4:1; con fotos arbitrarias de cada restaurante el velo debe ser opaco lo bastante para garantizar el contraste sin conocer la foto.
- **MariaDB**: las pruebas nuevas usan las mismas consultas que e; solo el CI de MariaDB confirmará que el orden de horarios y las colecciones se comportan igual que en SQLite.

## Lo construido (fase 2)

Decisiones confirmadas: opción A del script del pedido (copia adaptada de e) y el texto del panel de `EditRestauranteProfile` no se cambia. El prototipo se leyó de `C:\Users\thexl\Desktop\proyectos\bar` (no existe `C:\proyectos\bar` en este equipo).

- **Registro**: `f` ("Bar") en `Catalogos::plantillasSitio()`, por lo que aparece en el selector del panel y de "Mi restaurante". Vite: `f.css`, `sitio-bar.js` y `sitio-bar-pedido.js`.
- **Vistas**: `sitio/f.blade.php`, `menu/plantillas/f.blade.php` y `components/sitio/bar/{layout,cabecera,contacto,reservar,pedido,pie}.blade.php`. El controlador del menú elige `menu.plantillas.f` sin cambios.
- **Hoja** `resources/css/sitio/f.css`: variables `--bar-*` en `:root`, clases `bar-`, fuentes locales Bebas Neue y Space Grotesk (`public/fonts/sitio/`, con sus licencias OFL). El neón y la transición de vista solo con `prefers-reduced-motion: no-preference`.
- **Menú**: categorías agrupadas con filtro (mejora progresiva; sin JavaScript se ven todas), fila con nombre, línea punteada y precio, precio anterior tachado, etiquetas, "Agotado" como texto, y cada variante con su precio y su botón. Insignia "Mesa N" en el encabezado cuando `?mesa=` es válida.
- **Pedido**: mismas reglas que e (ver `docs/plantilla-cafeteria.md`): `pedidos_activos` + abierto ahora + `whatsapp_pedidos` de 8 a 20 dígitos; aviso si está cerrado; mesa editable; nota para la cocina; límites; enlace de 2000 caracteres; `localStorage` con clave `menuonl_cart_f_{slug}` solo con ids, cantidades y notas. Scripts: `sitio-bar-pedido.js` (DOM) y `sitio-bar-pedido-mensaje.js` (reglas puras).
- **Formato del mensaje**: debe mantenerse igual al de `menu.publico` y al de e. Son tres copias. `tests/Js/pedido-mensaje-bar.test.mjs` fija el formato de f y comprueba que el módulo de f y el de e generan el mismo enlace. Quien cambie uno debe cambiar los tres.
- **Reservas**: mismo formulario y contrato que a–e (ids `form-reserva`, `rs-*`, `reserva-msg`, `data-url`/`data-disp`), reutilizando `sitio.js` y `ReservaPublicaController`; enlace "Reservar" en el encabezado solo con reservas activas.
- **Sitio**: portada (nombre, eslogan y descripción tal cual, foto con velo opaco), destacado ("Nuevo" si lleva la etiqueta `nuevo`, "Recomendado" si no), "Destacados" (los platillos que entrega `SitioPublicoDatos::destacados`, sin los agotados), horario con el día de hoy marcado (zona del restaurante), reservar, contacto y ubicación.
- **Limitación conocida**: `SitioPublicoDatos` no entrega variantes; un platillo destacado con variantes muestra su precio base en el sitio (igual que en a–d). En el menú sí se listan todas las variantes.
- **Fuera de la plantilla**: `Catalogos.php`, `vite.config.js` y las expectativas de tres pruebas que contaban las plantillas (`PanelAjustesTest`, `SitioCafeteriaTest`, `SitioEditorialOscuroTest`).
