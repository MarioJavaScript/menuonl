# Plantilla G "CafePastel": análisis (Fase 1)

Prototipo aprobado: Pitaya, cafetería de postres y repostería (carpeta `cafe-pastel`, leída en `C:\Users\thexl\Desktop\proyectos\cafe-pastel`; `C:\proyectos\cafe-pastel` no existe en este equipo). El prototipo no trae `src/`: las fuentes son `js/app.js`, `css/estilos.css` y `data/`; `dist/` es la copia estática.

## Clave: g, no f

La rama `plantilla-bar` ya trae la plantilla `f` (Bar, commit `096e3bf`). Usar `f` para CafePastel habría sobrescrito sus vistas, su hoja y sus pruebas. Con la confirmación del dueño del proyecto, CafePastel es la clave **`g`**, con prefijo de clases `pas-` y variables `--pas-*`. Todos los archivos nuevos usan `pastel` en su nombre (`sitio/g.blade.php`, `menu/plantillas/g.blade.php`, `components/sitio/pastel/*`, `resources/css/sitio/g.css`, `resources/js/sitio-pastel*.js`).

## Cómo está hoy (lo que e y f ya resolvieron)

- **Menú por plantilla**: `MenuPublicoController` elige `menu.plantillas.{clave}` si la vista existe y, si no, `menu.publico`. Para g solo hace falta añadir `menu/plantillas/g.blade.php`; el controlador no se toca.
- **Panel**: el selector de "Plataforma, Restaurantes" (`panel.js`) y "Mi restaurante" (Filament) leen `Catalogos::plantillasSitio()`; registrar `g` basta.
- **Pedido y reservas**: e y f reutilizan `Restaurante::whatsappPedidosDigitos()`, `resources/js/sitio.js` y `ReservaPublicaController` sin cambios; cada plantilla trae su propio script de pedido con datos en atributos.
- **Acento**: `colorAcentoSeguro()` y `colorAcentoTexto()`; el layout valida el par con 4.5:1.

## Datos: de dónde sale cada bloque

| Bloque del prototipo | Campo / relación de Menubly | Estado |
|---|---|---|
| Encabezado: logo y nombre | `restaurantes.logo` y `nombre` | Listo; sin logo solo el nombre |
| Navegación Inicio, Menú, Reservar | `route('sitio.publico')`, `route('menu.publico')` | Listo; "Reservar" solo con reservas activas |
| Insignia "Mesa N" | `?mesa=` validado por `MenuPublicoController` | Solo en el menú (el sitio no recibe la mesa); acepta lo mismo que el servidor, no solo números |
| Portada: franja malva con borde rasgado, título, texto, botones | `nombre`, `eslogan`, `descripcion` | Ver "Textos de la portada" |
| Foto de portada en forma orgánica | `restaurantes.portada` | Listo; sin foto, el texto ocupa todo el ancho |
| Texto alternativo fijo de la portada del prototipo ("Taza de capuchino...") | No existe | Se sustituye por "Portada de {nombre}" |
| Texto "Estás en la mesa N. Puedes pedir desde tu celular." | `?mesa=` | Solo en el menú, y solo si se puede pedir; el sitio no lo muestra |
| Datos rápidos: "Hoy" con el horario del día y "Visítanos" | `horarios` y `contactos` tipo `direccion` | Listo; cada dato se omite si falta (el de hoy se omite si hoy está cerrado y no hay otro dato) |
| "Nuestras favoritas": tres tarjetas pastel por categoría con foto | Categorías de los `destacados` de `SitioPublicoDatos` (nombre de categoría y foto de un platillo) | Parcial: ver nota |
| Sección "Sobre {nombre}" con `foto_descripcion` | `descripcion` | La foto extra (`foto_descripcion`) no existe en Menubly: se omite la foto y el texto ocupa el ancho; la sección sin descripción se omite |
| "Postre del día" (platillo destacado) | Primer `destacado` visible, "Nuevo" si lleva la etiqueta `nuevo` | Se renombra "Nuevo" / "Recomendado" (ver "Textos") |
| Vista previa del menú (6 platillos) | `destacados` (hasta 6) | Parcial: ver riesgos |
| Horario con el día de hoy resaltado | `horarios` (lunes primero) y el día actual en la zona del restaurante | Listo; se omite si todos los días están cerrados |
| Ubicación: dirección y "Cómo llegar" | `contactos` tipo `direccion` y `mapa_url` | Listo; cada parte se omite si falta |
| Reservas: nombre, teléfono, personas, fecha, hora, nota | Formulario real reutilizado (ids, `data-url`, `data-disp`) | Listo; la hora es una lista de franjas libres del servidor; la confirmación simulada se sustituye por la real |
| Contacto: Llamar, WhatsApp, Instagram (+ Facebook en el pie) | `contactos` vía `SitioPublicoDatos` | Listo; cada botón se omite si falta su dato |
| Pie rasgado: nombre, dirección, enlaces, redes | Los mismos datos | Listo; "© año nombre" se sustituye por "Sitio creado con Menubly", como e y f |
| Menú: encabezado con eslogan, filtros por categoría | `categorias` visibles con traducción; `eslogan` | Listo; "Todos" solo con más de una categoría; el eslogan se omite si falta |
| Tarjeta de platillo: foto 4:3, nombre, descripción, etiquetas, precio | `productos`, traducción, `etiquetas`, `FormatoMoneda` | Listo; sin foto, recuadro neutro |
| Precio anterior tachado | `precio` cuando `precioVigente()` es menor | Listo |
| Variantes con un selector que cambia el precio | `producto_variantes` | Cambia: cada variante se lista con su precio y su botón "Agregar" (como e y f); sin selector ni JavaScript para cambiar el precio |
| Agotado: capa oscura con la palabra sobre la foto + aviso | `disponible` y `mostrar_agotados` | Listo; con texto, no solo color |
| Pedido: sección "Tu pedido" al final y barra fija | Pedido de e/f (barra "Ver pedido" + `<dialog>`) | Cambia el contenedor, no la lógica: ver "Cajón del pedido" |
| Pedido: sessionStorage y notas de 120 caracteres | `localStorage` solo con ids, cantidades y notas; notas de 80 | Se sigue lo decidido en e: `menuonl_cart_g_{slug}` |
| Selector de idioma | `restaurante_idiomas` | No existe en el prototipo; se añade uno mínimo si hay más de un idioma, como e y f |
| Acento (`color_acento`) | `colorAcentoSeguro()` / `colorAcentoTexto()` | Ver "Acento" |
| Aviso "Cargando..." y "Necesita JavaScript" | Prototipo | No aplican: todo se renderiza en el servidor |
| Fotos de Pitaya (`assets/img/*.webp`) | Prototipo | No se copian; se usan las del restaurante |
| Datos inventados de Pitaya (dirección, teléfono, redes) | Prototipo | No se llevan |

No hay testimonios, datos nutricionales ni otro bloque que Menubly no guarde, salvo la foto extra de "Sobre", que se omite.

Nota sobre "Nuestras favoritas": `SitioPublicoDatos` no entrega la lista de categorías, solo el nombre de la categoría de cada destacado. La sección se arma con las categorías distintas de los destacados visibles (hasta tres, cada una con la foto de su primer destacado que tenga foto) y se omite si salen menos de dos. Las tarjetas enlazan al menú, sin ancla por categoría. Alternativa más fiel, que no se hace sin aprobación: añadir una clave `categorias` a `SitioPublicoDatos::para()` (aditiva; las vistas de a a f la ignoran), con id y nombre de cada categoría, para enlazar a cada una y mostrar las tres primeras aunque no tengan destacados.

## Textos de la portada

Opciones:

1. **Derivarlos de los campos que ya existen (recomendada).** El prototipo usa el eslogan como título y el nombre en un rótulo cursivo ("Bienvenidos a {nombre}"). Aquí: el nombre es el `h1`; el eslogan, si existe, va como rótulo cursivo encima del título (sin el "Bienvenidos a" fijo); la descripción va en la sección "Sobre". Cada uno se omite si está vacío. Sin migración ni formulario nuevo.
2. **Campos nuevos** para los rótulos cursivos de cada sección ("Para endulzar el día", "Un vistazo", "Escríbenos"...). No hace falta: esos rótulos son texto fijo de interfaz (el prototipo los tiene en el JS), igual que en e. Quedan como texto fijo de la plantilla.

Recomiendo la 1. Los rótulos cursivos de las secciones se mantienen, pero solo los que no afirman nada sobre el negocio ("Escríbenos", "Guárdanos un lugar", "Un vistazo", "Tu mesa, a tu ritmo"); "Hecho cada día", "Postre del día" y "Hoy en la vitrina" afirman cosas que Menubly no sabe y se omiten o se reemplazan por "Nuevo" / "Recomendado".

## Menú por plantilla

| Plantilla | Vista del menú | Diseño |
|---|---|---|
| a, b, c, d | `menu.publico` | `menuonl.css` + estilo del restaurante, sin cambios |
| e | `menu.plantillas.e` | Propio (Cafetería), sin cambios |
| f | `menu.plantillas.f` | Propio (Bar), sin cambios |
| g | `menu.plantillas.g` (nueva) | Propio (CafePastel): `g.css`, sin `menuonl.css`, sin Tailwind, sin estilo del restaurante |

## Diseño y decisiones propias

- **Hoja `g.css`**: se parte de `estilos.css` del prototipo (ya usa clases `pas-`); las variables `--mo-*` pasan a `--pas-*`. Colores solo en `:root`; ni las vistas ni el resto de la hoja llevan `#hex`, `rgb()` ni `rgba()`.
- **Borde rasgado**: el prototipo lo hace con una máscara CSS cuyo SVG va en un `data:` URI (contiene `http://www.w3.org/2000/svg`, que dispararía la prueba de "ningún recurso externo"). Se sustituye por `clip-path: polygon(...)` con los mismos puntos del contorno, sin URL ni `data:`.
- **Tipografías**: DM Serif Display 400, DM Sans 400/500/700 y Great Vibes 400 (subconjunto latino, de los archivos del propio prototipo) en `public/fonts/sitio/` con `OFL-dm-sans.txt`, `OFL-dm-serif-display.txt` y `OFL-great-vibes.txt`. Great Vibes solo a 1.75 rem o más (el propio prototipo lo advierte).
- **Movimiento**: `@view-transition { navigation: auto; }`, las transiciones y `scroll-behavior: smooth` van en `prefers-reduced-motion: no-preference`.
- **Estructura**: `components/sitio/pastel/{layout,cabecera,contacto,reservar,pedido,pie}.blade.php`, `sitio/g.blade.php` y `menu/plantillas/g.blade.php`, copiando la forma de f.
- **Menú sin JavaScript**: todo se renderiza en el servidor; el filtro por categoría es mejora progresiva (sin JS se ven todas las categorías) y el botón "Pedir por WhatsApp" sin pedido armado queda como respaldo.
- **Cajón del pedido**: el prototipo lo resuelve como una sección al final de la página. Aquí se usa el patrón de e y f (barra fija inferior "Ver pedido" + `<dialog>`) con el aspecto del prototipo: panel durazno con tarjetas crema por línea, botones de cantidad circulares de 44 px, barra cacao oscuro con botón crema, y los campos Mesa y "Nota para la cocina".
- **Reservas**: se conserva el formulario real; rejilla de dos columnas, errores en el color de error de la hoja con contraste >= 4.5:1, y los elementos que crea `sitio.js` (`.error`, enlace `btn btn-alt`) estilados en `g.css`.
- **Acento**: el acento del restaurante cambia solo fondos de botones, marcas y subrayados, con el texto de `colorAcentoTexto()` y solo si el par llega a 4.5:1. El texto sobre las franjas y tarjetas pastel usa siempre los colores base de la hoja, porque su contraste no depende de un color ajeno. (El prototipo exige además 4.5:1 del acento contra el texto crema; aquí el texto del botón es el de `colorAcentoTexto()`, que ya garantiza la lectura.)
- **"Menú"** en toda la interfaz; nunca "Carta".

## Script del pedido: dos opciones

**A. Copiar el de f/e y adaptarlo (elegida).** `resources/js/sitio-pastel-pedido.js` y `sitio-pastel-pedido-mensaje.js`, con atributos `data-pas-*` y clave `menuonl_cart_g_{slug}`, y su prueba de Node en `tests/Js/`. e, f y a a d no cambian. Riesgo: el formato del mensaje queda en cuatro sitios (`menu.publico`, e, f, g) que hay que mantener iguales; se mitiga con una prueba de Node que fija el formato de g y con un comentario en cada archivo.

**B. Módulo compartido.** Cambia los archivos de e y f (otro hash de bundle) y obliga a parametrizar los atributos; no se hace sin confirmación porque toca la salida de plantillas ya aprobadas.

Se sigue A: no toca la salida de e ni de f.

## Qué se toca fuera de la plantilla nueva (estrictamente necesario)

1. `app/Services/Catalogos.php`: registrar `g` en `plantillasSitio()`.
2. `vite.config.js`: registrar `g.css` y los scripts de g.
3. Pruebas que enumeran o cuentan las plantillas y fallarían al registrar `g`: las que f ya actualizó (`PanelAjustesTest`, `SitioEditorialOscuroTest`, `SitioCafeteriaTest`, y las de Bar si cuentan plantillas).

Sin cambios: `frontend/`, `publico.blade.php`, `dish-card.blade.php`, `menu.js`, `sitio.js`, `MenuPublicoController`, `SitioPublicoDatos`, `Restaurante` ni las plantillas a a f. El texto de ayuda del panel que menciona solo "Cafetería" no se cambia.

## Migraciones y datos existentes

Ninguna migración, ningún dato existente cambia y ninguna otra plantilla cambia de comportamiento. No hay motivo para detenerse.

## Riesgos y puntos a verificar

- **Vista previa**: `destacados` no expone variantes ni orden por categoría; un producto con variantes muestra su precio base.
- **Contraste sobre la foto de portada**: la portada del prototipo es una franja de color con la foto al lado, y el texto nunca va sobre la foto; se mantiene así, lo que evita el problema.
- **Rótulo "Agotado" sobre la foto**: lleva capa oscura (contraste de la capa, no de la foto).
- **Día de hoy**: se calcula en el servidor con la zona del restaurante; la página del sitio se cachea 30 segundos.
- **MariaDB**: las pruebas nuevas usan las mismas consultas que e y f; solo el CI de MariaDB confirma el orden de horarios y colecciones.
