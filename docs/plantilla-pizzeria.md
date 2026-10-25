# Plantilla H "Pizzería": análisis (Fase 1)

Prototipo aprobado: Pizzería Lupita (`C:\Users\thexl\Desktop\proyectos\pizzeria`). Fuentes leídas: `src/index.html`, `src/menu.html`, `src/js/app.js`, `src/css/estilos.css`, `src/data/restaurante.json`, `README.md`, `REPORTE.md`, `CREDITOS.md`. `dist/` es copia exacta de `src/` (mismos tamaños de archivo). Modelo técnico: plantilla g (CafePastel), commit `e3cd083`.

## Clave y nombres

- Clave **`h`** (la columna `restaurantes.sitio_plantilla` es `string(1)` y `h` cabe; sin migración). Nombre visible **Pizzería**. Prefijo de clases `piz-`, variables `--piz-*`.
- Archivos nuevos: `sitio/h.blade.php`, `menu/plantillas/h.blade.php`, `components/sitio/pizzeria/{layout,cabecera,contacto,reservar,pedido,pie}.blade.php`, `resources/css/sitio/h.css`, `resources/js/sitio-pizzeria{,-pedido,-pedido-mensaje}.js`, `tests/Js/pedido-mensaje-pizzeria.test.mjs`, pruebas `SitioPizzeriaTest`, `PedidoWhatsappPizzeriaTest`, `ReservasPizzeriaTest`.

## Datos: de dónde sale cada bloque

| Bloque del prototipo | Campo / relación de Menubly | Estado |
|---|---|---|
| Cabecera: franja de mantel, logo, nombre | `logo`, `nombre` | Listo; sin logo solo el nombre |
| Navegación Inicio, Menú, Reservar | `route('sitio.publico')`, `route('menu.publico')` | Listo; "Reservar" solo con reservas activas |
| Sello "Mesa N" | `?mesa=` validado por `MenuPublicoController` | Solo en el menú (el sitio no recibe la mesa) |
| Portada: título, eslogan, descripción, "Hoy: horario", botones | `nombre`, `eslogan`, `descripcion`, `horarios`, `whatsapp_digitos` | Listo; cada parte se omite si falta |
| Foto de portada en plato circular con dirección al pie | `portada`, `direccion` | Listo; sin foto, el texto ocupa el ancho. La dirección sobre la foto se omite (el texto sobre foto no se puede medir); la dirección va en "Ubicación" |
| Botón "Escribir por WhatsApp" (saludo prellenado) | `whatsapp_digitos` | Listo; solo con número válido |
| "Pizza de la casa" (platillo destacado) | Primer `destacado` visible: el marcado "Nuevo" o, si no hay, el primero con foto | Se renombra "Nuevo" / "Recomendado" (Menubly no sabe si es pizza ni "de la casa"). Los tamaños con precio del prototipo no existen en `destacados`: se muestra el precio base |
| "Lo que sale del horno" (vista previa, 6) | `destacados` (hasta 6) | Listo; el rótulo es texto fijo de interfaz de una plantilla de pizzería. Un platillo con variantes muestra su precio base; "Desde" no se inventa |
| Horario con el día de hoy | `horarios` y día actual en la zona del restaurante | Listo; se omite si todos los días están cerrados |
| Contacto: Llamar, WhatsApp, Instagram | `contactos` vía `SitioPublicoDatos` | Listo; cada botón se omite si falta su dato |
| Ubicación: dirección y "Cómo llegar" | `direccion`, `mapa_url` | Listo; cada parte se omite si falta |
| Reservas | Formulario real reutilizado (`sitio.js`, `data-url`, `data-disp`) | Listo; la confirmación simulada se sustituye por la real; la hora es una lista de franjas libres del servidor |
| Pie: nombre, dirección, teléfono, redes, "© año" | Los mismos datos | Listo; "© año nombre" pasa a "Sitio creado con Menubly", como e, f y g |
| Menú: título, filtros por categoría | `categorias` visibles con traducción; `eslogan` | Listo; "Todo" solo con más de una categoría; sin JavaScript se ven todas |
| Tarjeta: foto circular, nombre, etiquetas, descripción, precio | `productos`, traducción, `etiquetas`, `FormatoMoneda` | Listo; sin foto, recuadro neutro circular |
| Precio anterior tachado | `precio` si `precioVigente()` es menor | Listo |
| Selector de tamaño que cambia el precio | `producto_variantes` | **Cambia** (decisión ya tomada en e, f y g): cada variante se lista con su precio y su botón "Agregar"; sin selector ni JavaScript de precio |
| Agotado: etiqueta y sin botón | `disponible`, `mostrar_agotados` | Listo; con texto, no solo color |
| Pedido: barra fija y diálogo | Pedido de e/f/g (barra + `<dialog>`) | Misma lógica; aspecto del prototipo (ver "Diseño") |
| Pedido en `sessionStorage`, notas de 140 | `localStorage` solo con ids, cantidades y notas (80) | Se sigue lo decidido en e: clave `menuonl_cart_h_{slug}` |
| Mensaje de WhatsApp recortable a 1800 caracteres | Formato único de `menu.publico`/e/f/g | Se usa el formato de Menubly, no el del prototipo |
| Reservas con validación y confirmación simuladas, `?demo=minimo`, "Cargando...", "Necesita JavaScript" | Prototipo | No aplican: todo se renderiza en el servidor |
| Selector de idioma | `restaurante_idiomas` | No existe en el prototipo; se añade uno mínimo si hay más de un idioma, como en e, f y g |
| Acento (`color_acento`) | `colorAcentoSeguro()` / `colorAcentoTexto()` | Ver "Acento" |
| Fotos, nombre, teléfono, dirección y redes de Lupita | Prototipo | No se llevan |

No hay testimonios ni datos nutricionales; no hay nada que omitir por ese motivo. Lo que falta o se omite: dirección sobre la foto de portada, tamaños con precio en el destacado, "Desde", selector de tamaño con cambio de precio, confirmación simulada de reserva.

## Textos de la portada

Opciones:

1. **Derivarlos de los campos que ya existen (recomendada).** `h1` = nombre; eslogan debajo en cursiva (como el prototipo); descripción en la portada si existe; "Hoy: horario" calculado. Cada uno se omite si está vacío. Sin migración ni formulario nuevo.
2. Campos nuevos para los textos de portada. No hacen falta: el prototipo solo usa nombre, eslogan y descripción. No se propone.

Se sigue la 1. Los rótulos fijos ("Lo que sale del horno", "Reserva tu mesa", "Horario", "Contacto", "Ubicación") son texto de interfaz y no afirman nada sobre el negocio.

## Aviso del panel (EditRestauranteProfile)

Hoy dice: *"Diseño de tu sitio (/tu-restaurante) y de su menú. La plantilla Cafetería trae sus propios colores y tipografías y no usa el estilo visual de arriba."* Ya no es exacto: Bar, CafePastel y la nueva Pizzería también lo hacen.

Propuesta (solo texto, `app/Filament/Pages/Tenancy/EditRestauranteProfile.php:66`):

> "Diseño de tu sitio (/tu-restaurante) y de su menú. Las plantillas Cafetería, Bar, CafePastel y Pizzería traen sus propios colores y tipografías y no usan el estilo visual de arriba."

**No lo cambio sin su aprobación.** Ninguna prueba lee ese texto (revisé `tests/`). La plantilla funciona igual con o sin el cambio.

## Menú por plantilla

| Plantilla | Vista del menú | Diseño |
|---|---|---|
| a, b, c, d | `menu.publico` | `menuonl.css` + estilo, sin cambios |
| e, f, g | `menu.plantillas.{clave}` | Propio, sin cambios |
| h | `menu.plantillas.h` (nueva) | Propio (Pizzería): `h.css`, sin `menuonl.css`, sin Tailwind, sin estilo del restaurante |

`MenuPublicoController` ya elige `menu.plantillas.{clave}` si la vista existe; no se toca.

## Diseño y decisiones propias

- **Hoja `h.css`**: se parte de `estilos.css` del prototipo (ya usa `piz-`; variables `--mo-*` pasan a `--piz-*`). Colores base en `:root`; los derivados del acento (mantel, hover, cuadros) van en `.piz-pagina` porque la vista inyecta `--piz-acento` en `<body>` y un derivado declarado en `:root` no vería ese valor. Ni las vistas ni el resto de la hoja llevan `#hex`, `rgb()` ni `rgba()`.
- **Mantel de cuadros**: dos gradientes CSS (sin imágenes ni `data:`), igual que el prototipo.
- **Tipografías** (de los archivos del propio prototipo, subconjunto latino): Fraunces 600/700 e itálica 400, Nunito Sans 400/700, en `public/fonts/sitio/` con `OFL-fraunces.txt` y `OFL-nunito-sans.txt`.
- **Movimiento**: `@view-transition` y `scroll-behavior: smooth` solo en `prefers-reduced-motion: no-preference`.
- **Cajón del pedido** (el prototipo ya usa barra fija + `<dialog>`): barra fija inferior de ancho completo con borde punteado tomate; el diálogo es una tarjeta centrada con borde cacao en pantallas anchas y hoja inferior en celular; líneas separadas con puntos; botones de cantidad circulares de 44 px; campos Mesa y "Nota para la cocina" como en g. Botón de enviar en verde albahaca.
- **Formulario de reserva**: el real, en una banda papel claro con borde punteado tomate (como la sección de reservas del prototipo), dos columnas desde 640 px, errores en el color de error con contraste >= 4.5:1.
- **Acento**: el acento del restaurante reemplaza al tomate solo en fondos de botones, filtro activo, cuadros del mantel y banda del destacado, y solo si el par acento/`colorAcentoTexto()` llega a 4.5:1 (misma lógica que g). Textos de precio, etiquetas y bandas usan siempre los colores base. El verde de WhatsApp no cambia.
- **Banda del destacado**: fondo tomate fijo (no el acento) con texto papel (5.0:1 comprobado); así el acento ilegible o ajeno nunca la afecta.
- **"Menú"** en toda la interfaz; nunca "Carta".

## Script del pedido

**A. Copiar el de g y adaptarlo (elegida, como pidió).** `sitio-pizzeria-pedido.js` y `sitio-pizzeria-pedido-mensaje.js` con atributos `data-piz-*`, clave `menuonl_cart_h_{slug}` y su prueba de Node en `tests/Js/`, que `PedidoWhatsappJsTest` ya recoge (lista de archivos armada en PHP, compatible con Node 22). e, f y g no cambian. No tengo una razón para proponer otra cosa.

## Qué se toca fuera de la plantilla nueva (estrictamente necesario)

1. `app/Services/Catalogos.php`: registrar `h` en `plantillasSitio()`.
2. `vite.config.js`: registrar `h.css` y los scripts de h.
3. Pruebas que cuentan o enumeran plantillas y fallarían: `PanelAjustesTest` (`assertJsonCount(7, 'data.plantillas')` pasa a 8) y los tres `assertSame(['a', ..., 'g'], ...)` de `SitioBarTest`, `SitioCafeteriaTest` y `SitioEditorialOscuroTest` (sumar `'h'`). Más `SitioPastelTest`, que tiene el mismo `assertSame`.

Sin cambios: `frontend/`, `MenuPublicoController`, `SitioPublicoDatos`, `Restaurante`, `sitio.js`, `menu.js`, `menuonl.css`, las plantillas a a g y los temas.

## Migraciones y datos existentes

Ninguna migración, ningún dato existente cambia, ninguna otra plantilla cambia de comportamiento. No hay motivo para detenerse.

## Reglas de pruebas aplicadas desde el inicio (lecciones del CI)

- Todo valor que se guarde cabe en su columna. `color_acento` es `string(7)`; la prueba lee el largo desde la migración (`*apariencia_to_restaurantes*`) y comprueba que el valor hostil guardado no lo excede. Los valores más largos se prueban en memoria con `new Restaurante([...])`, sin guardar. `whatsapp_pedidos` es `string(20)`: se comprueba igual antes de guardar cualquier valor.
- La prueba de Node usa el formato `*.test.mjs` que arma `PedidoWhatsappJsTest` en PHP (`node --test archivo1 archivo2`), nunca una carpeta.

## Riesgos y puntos a verificar

- **Texto sobre foto**: ningún texto va sobre una foto salvo la capa "Agotado", que no se usa en h (el agotado lleva etiqueta de texto sobre fondo sólido).
- **Día de hoy**: se calcula en el servidor con la zona del restaurante; la página del sitio se cachea 30 s.
- **MariaDB**: las pruebas nuevas usan las mismas consultas que g; solo el CI de MariaDB confirma el orden de horarios y colecciones.
