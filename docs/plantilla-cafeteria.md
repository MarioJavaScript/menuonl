# Plantilla E "Cafetería": análisis

Prototipo aprobado: `C:\proyectos\prueba` (Mezquite). Este documento resume el análisis previo a construir la plantilla `e`.

## Cómo está hoy

- **Sitio** (`/{slug}`): `SitioPublicoController` pide los datos a `SitioPublicoDatos::para()` y renderiza `sitio.{plantilla}`. La clave sale de `restaurantes.sitio_plantilla`; si no existe en `Catalogos::plantillasSitio()` cae a `a`. Cada vista usa `<x-sitio.layout>`, que carga solo `resources/css/sitio/{plantilla}.css` y `resources/js/sitio.js`.
- **Menú** (`/{slug}/menu`): `MenuPublicoController` arma `categorias`, `destacados`, `contactos` y `horarios` (con caché) y renderiza siempre `menu.publico`, que usa `<x-layout.publico>` (Tailwind `app.css` + `public/css/menuonl.css`, tema por `data-tema`). Hoy el menú tiene un solo diseño y obedece al estilo (`tema`) del restaurante, no a la plantilla del sitio.
- **Panel**: el selector de plantillas se dibuja en `resources/js/panel.js` con la lista que devuelve `AjustesController` desde `Catalogos::plantillasSitio()`. Registrar `e` ahí basta para que sea seleccionable.
- **Acento**: `Restaurante::colorAcentoSeguro()` (hex de 6 dígitos o `null`) y `colorAcentoTexto()` (blanco u oscuro legible sobre ese acento). La plantilla D ya los usa.

## Menú por plantilla: la forma más simple

En `MenuPublicoController`, al final, elegir la vista así:

```
$vista = view()->exists('menu.plantillas.'.$plantilla) ? 'menu.plantillas.'.$plantilla : 'menu.publico';
```

donde `$plantilla` es `sitio_plantilla` validada contra `Catalogos::plantillasSitio()`. Solo existirá `menu/plantillas/e.blade.php`; para `a`, `b`, `c` y `d` la vista no existe y todo sigue pasando por `menu.publico` sin cambio alguno. Una plantilla futura que quiera su menú solo agrega su archivo. El controlador, la caché, el idioma y los encabezados no cambian.

La vista `e` usa un layout propio (`components/sitio/cafeteria`) con su hoja y su script; no carga `menuonl.css` ni Tailwind, así que no depende del estilo del restaurante.

## Datos: de dónde sale cada bloque

| Bloque del prototipo | Campo / relación de Menuonl | Estado |
|---|---|---|
| Nombre (cabecera, pie) | `restaurantes.nombre` | Listo |
| Slogan | `restaurantes.eslogan` | Listo; se omite si está vacío |
| Descriptor del pie ("Café y bebidas frías en Hermosillo") | `restaurantes.descripcion` (recortada) | Parcial: es un texto largo; se usa solo si es corto, si no se omite |
| Franja de aviso superior | No existe | Falta; se omite |
| Foto de portada (bebida en arco) | `restaurantes.portada` | Listo; sin foto, recuadro neutro |
| Textura de piedra de la portada | Decoración del prototipo | No es dato; se reemplaza por color plano de la hoja (no se copian las fotos del prototipo) |
| Dos palabras gigantes + palabra en cursiva | Derivadas de nombre y eslogan (ver abajo) | Sin campo nuevo |
| Botón "Ver el menú" | `route('menu.publico')` | Listo |
| Categorías (filtros) | `categorias` visibles con su traducción | Listo; "todas" se agrega por código |
| Platillos: nombre, descripción | `producto_traducciones` (idioma, con respaldo) | Listo |
| Platillos: foto | `productos.imagen` | Listo; sin foto, recuadro neutro |
| Platillos: precio | `Producto::precioVigente()` con `FormatoMoneda` | Listo |
| Precio anterior tachado | `productos.precio` cuando hay `precio_oferta` menor | Listo |
| Variantes con precio | `producto_variantes` | Existe en Menuonl, no en el prototipo; se muestran como lista bajo el platillo |
| Etiquetas dietéticas / agotado | `etiquetas`, `disponible` | Existe en Menuonl, no en el prototipo; se muestran como marcas |
| Banda "Nueva bebida" (platillo destacado) | Primer producto `destacado` con foto y, si lo hay, con etiqueta `nuevo` | Parcial |
| Datos de la banda (Jarabe, Base, Tamaño) | No existe | Falta; se omite. Se muestran las etiquetas del platillo |
| Textos "Nueva", "de temporada", "bebida" | Fijos del prototipo | Se reemplazan por "Nuevo" (si tiene etiqueta `nuevo`) o "Recomendado"; nada inventado |
| Pedido simulado en memoria | El menú real pide por WhatsApp con un carrito propio de `menu.publico` | Hecho en la fase 2: E trae su propio carrito (ver "Pedido y reservas: lo construido") |
| Dirección | `contactos` tipo `direccion` | Listo |
| Horario | `horarios` | Listo; se omite si todos los días están cerrados |
| Teléfono | `contactos` tipo `telefono` | Listo |
| WhatsApp | `whatsapp_pedidos` o `contactos` tipo `whatsapp` | Listo |
| Instagram | `contactos` tipo `instagram` | Listo |
| Facebook / mapa | `contactos` tipo `facebook`, coordenadas | Existe; se agrega como enlace |
| Pie con nota de ejemplo | "Sitio creado con Menuonl" como las otras plantillas | Listo |
| Reservaciones | `reservas_activas` | Hecho en la fase 2: sección `#reservar` con el mismo servidor y `sitio.js` |
| Idiomas del menú | `restaurante_idiomas` | El prototipo no los tiene; se agrega un selector mínimo si hay más de uno |

## Las dos palabras gigantes

Opciones:

1. **Derivarlas del nombre y el eslogan, sin campos nuevos (recomendada).** Nombre de varias palabras: la última palabra va a la derecha y el resto a la izquierda (igual que la plantilla D). Nombre de una sola palabra: va sola en un lado y la escena se centra. El eslogan, si es corto, va como palabra en cursiva; el eslogan completo va debajo con el botón. Sin textos inventados y funciona hoy para cualquier restaurante.
2. **Campos nuevos** (`portada_palabra_izquierda`, `portada_palabra_derecha`, `portada_acento`). Más fiel al prototipo, pero requiere migración, formulario en el panel y datos nuevos que llenar. No se hace sin tu aprobación.

Recomiendo la 1. Si luego quieres control editorial, la 2 se puede sumar sin romper la 1.

## Decisiones tomadas

- El acento del restaurante solo cambia el fondo de botones y marcas, con el texto de `colorAcentoTexto()`. Los títulos y textos sobre superficies claras usan siempre el arcilla de la plantilla, así el contraste de 4.5:1 no depende de un color ajeno.
- (Sustituido por la fase 2) Al principio E solo ofrecía un enlace "Pedir por WhatsApp"; ahora trae carrito y reservas.
- Sin migraciones, sin datos nuevos y sin cambiar el comportamiento de las plantillas `a`, `b`, `c` y `d`.

## Cambios fuera de la plantilla E (estrictamente necesarios)

1. `Catalogos::plantillasSitio()`: registrar `e`.
2. `vite.config.js`: registrar la hoja y el script de `e`.
3. `MenuPublicoController`: elegir `menu.plantillas.{plantilla}` si existe (para `a` a `d` no existe, sin efecto).
4. `tests/Feature/PanelAjustesTest.php`: el conteo de plantillas pasa de 4 a 5.

## Pedido por WhatsApp y reservas en la plantilla E: análisis (fase 1)

Esta sección sustituye a las líneas anteriores que dicen que E no lleva pedido ni formulario de reservas. No requiere migraciones ni cambiar datos existentes.

### Cómo funciona hoy el pedido en `menu.publico`

- **Ruta y controlador**: `GET /{slug}/menu` (`menu.publico`, límite `menu-publico`, 300 por minuto y por IP) llega a `MenuPublicoController::mostrar()`. No hay ruta ni endpoint de pedido: el pedido no se guarda, el servidor nunca lo recibe y todo ocurre en el navegador.
- **Datos que entrega el controlador**: `restaurante`, `idioma`, `mesa`, `estaAbierto` (tiempo real, fuera de la caché), `categorias`, `destacados`, `contactos` y `horarios`. Con "Mostrar platillos agotados" desactivado, los platillos agotados se excluyen en la consulta (categorías y destacados); activado, llegan con `disponible = false`.
- **Mesa**: `?mesa=` se valida en el servidor con `/\A[A-Za-z0-9 _-]{1,20}\z/` (tras `trim`). Si no cumple, `mesa` es `null`. No tiene que ser un número. La vista la muestra como insignia "Mesa X" y como valor inicial del campo editable "Mesa".
- **Cuándo se puede pedir**: `$puedePedir = pedidos_activos && estaAbierto && filled(whatsapp_pedidos)`. Si es falso no hay botones "Agregar", ni barra, ni cajón; se muestra un aviso (pedidos desactivados, cerrado con la próxima apertura, o sin número).
- **Vistas**: `menu/publico.blade.php` (barra flotante "Ver pedido", cajón "Tu pedido", aviso de vaciar, aviso "Abrimos WhatsApp con tu pedido") y `menu/partials/dish-card.blade.php` (botón "Agregar" por platillo y por variante). Un platillo o variante agotado muestra "Agotado" en lugar del botón.
- **JavaScript**: el carrito vive en un `<script>` en línea dentro de `publico.blade.php`, con funciones globales (`cartAddItem`, `cartUpdateQty`, `enviarPedidoWhatsApp`, ...) que las vistas llaman con atributos `onclick`. `resources/js/menu.js` solo aporta Alpine CSP (hojas arrastrables, detalle del platillo, cabecera). El carrito se guarda en `localStorage` con la clave `menuonl_cart_{slug}`, y guarda **también nombre y precio** de cada línea.
- **Datos del servidor hacia el JS**: nombre del restaurante, moneda, número (solo dígitos) e idioma van con `@js()`; en cada botón, id, nombre, variante y precio van con `@js()`/número. El nombre y el precio que usa el mensaje salen de lo guardado en `localStorage`, no se vuelven a pedir al servidor.
- **Formato del mensaje** (lo que se codifica con `encodeURIComponent` y se abre en `https://wa.me/{dígitos}?text=...`):

  ```
  Hola, quiero pedir en *{restaurante}*:

  *Mesa:* {mesa o "Para llevar"}

  *Pedido:*
  • {cantidad}x {platillo} ({variante}) - {subtotal}
    _Nota: {nota}_

  *Nota para la cocina:* {nota general}      (solo si se escribió)

  *Total:* {total}
  ```

  El importe usa `Intl.NumberFormat` con el idioma y la moneda del restaurante, igual de ambiguo que `FormatoMoneda` (agrega el código de moneda si el símbolo es `$` y no es MXN).
- **Validación hoy**: en el servidor, el número solo se limpia a dígitos al renderizar. La regla de 8 a 20 dígitos solo se aplica al guardar el campo en Filament (`regex:/^[0-9]{8,20}$/`); el panel por API (`AjustesController`) acepta además `+`, espacios, paréntesis y guiones y guarda el valor tal cual. No hay límite de largo para las notas, no se recorta el enlace y el HTML del cajón se arma con `innerHTML` (con `escapeHtml`) y manejadores `onclick`.
- **Pruebas existentes**: `MenuPublicoTest` (pedido bloqueado por pedidos desactivados o por cerrado, marcas del carrito), `MenuPublicoContextoJsTest` (nombres hostiles dentro del `<script>`), `AdversarialPublicMenuTest`.

### Qué hace hoy el botón "Pedir por WhatsApp" de E

En `menu/plantillas/e.blade.php` es un enlace simple a `https://wa.me/{dígitos}` **sin texto de pedido**. Aparece si `pedidos_activos` y hay `whatsapp_digitos`, donde `SitioPublicoDatos::para()` calcula `whatsapp_digitos` desde `whatsapp_pedidos` **o, si está vacío, desde el contacto de tipo WhatsApp**.

### Diferencias entre lo que describiste y el comportamiento actual

| Tema | Menú actual (`menu.publico`) | Botón actual de E | Lo que describiste |
|---|---|---|---|
| Horario | Solo se pide si el restaurante está abierto ahora | No lo revisa | Solo menciona interruptor y número |
| Origen del número | Solo `whatsapp_pedidos` | `whatsapp_pedidos` o, si falta, el contacto WhatsApp | "El campo de WhatsApp del restaurante" |
| Validación del número | Quita lo que no sea dígito; no revisa el largo | Igual | "Se valida y limpia en el servidor" |
| Mesa | Campo editable con el valor de `?mesa=` y, si queda vacío, "Para llevar" | No aplica | "Mesa si viene en `?mesa=N`" |
| Nota general | Hay "Nota para la cocina" | No aplica | No la menciona |
| Precios y nombres | Se guardan en `localStorage` y de ahí se arma el mensaje | No aplica | Deben salir del servidor |
| Notas y enlace | Sin límite de largo, sin recorte del enlace, con `innerHTML` y `onclick` | No aplica | Limpiar y limitar notas, recortar el enlace, sin `innerHTML` |

Lo que sí coincide: interruptor "Activar pedidos por WhatsApp", campo de WhatsApp, "Mostrar platillos agotados", variantes, nota por platillo, formato del mensaje, `encodeURIComponent`, platillos y variantes agotados que no se pueden agregar y moneda con el mismo formato.

### Reservas públicas del sitio en las otras plantillas

- **Componente**: `resources/views/sitio/partes/reservar.blade.php`, incluido en la sección `#reservar` de A, B, C y D. Solo dibuja el formulario si `reservas_activas`; el bloque de WhatsApp y llamada aparece siempre que haya contacto. `SitioPublicoDatos::para()` ya entrega `reservas_activas`.
- **Formulario**: `nombre`, `telefono`, `fecha`, `personas`, `hora`, `nota`, un campo trampa `sitio_web` y un `ts` oculto con la hora de renderizado. `data-url` y `data-disp` apuntan a `/api/v1/sitio/{slug}/reservas` y `/disponibilidad`. Los ids (`form-reserva`, `rs-*`, `reserva-msg`) los usa `resources/js/sitio.js`.
- **JavaScript**: `sitio.js` (sin dependencias) pide las horas libres al elegir fecha o personas, envía el JSON y muestra el resultado con `textContent`, con un enlace "Avisar por WhatsApp". Solo depende de los ids, los nombres de campo, los `data-*` y las clases `error`, `btn` y `btn-alt`.
- **Reglas en el servidor** (`ReservaPublicaController` + `ReservasPublicas`): nombre 2 a 100 con letras, espacios, puntos y guiones; teléfono de 8 a 20 con `+`, dígitos, espacios, paréntesis y guiones; personas entre 1 y `max_personas`; fecha y hora con formato; nota de hasta 255 con `strip_tags`. Se revalida fecha (no pasada, hasta `max_dias`), día con servicio, franja de la rejilla, anticipación mínima y cupo, dentro de una transacción con bloqueo del restaurante. La reserva nace `pendiente` con origen `sitio`. El restaurante siempre sale del slug de la URL.
- **Protección contra spam**: campo trampa (responde 201 y no guarda), `ts` entre 2 segundos y 12 horas, límite `reservas-publicas` en el POST (3 por minuto por IP y 10 por hora por IP y slug) y `menu-publico` en la disponibilidad.
- **CSP del sitio**: `default-src 'self' 'unsafe-inline'; form-action 'self'`; el `fetch` a `/api/v1/...` es del mismo origen.
- **Cómo reutilizarlo en E**: el servidor y `sitio.js` se reutilizan sin cambios. E solo necesita su propio marcado con clases `cal-`, conservando ids, nombres de campo y `data-*`, cargar `sitio.js` en la página del sitio, y estilar en `e.css` los elementos que `sitio.js` crea (`.error` y el enlace `btn btn-alt`). Se pasa `reservas_activas` al componente y se agrega "Reservar" en la cabecera solo si está activo. En el menú de E, el acceso "Reservar" apunta a `route('sitio.publico', ...)` con `#reservar`.

### Dos opciones para el carrito

**A. Extraer lo común** a un script o componente compartido por `menu.publico` y E. El carrito actual es un `<script>` en línea con funciones globales y `onclick` repartidos entre `publico.blade.php` y `dish-card.blade.php`, y usa `innerHTML`. Compartirlo exige mover el script a un archivo y quitar los `onclick` de las tarjetas, es decir, **cambia el HTML y el comportamiento de A, B, C y D** (más el riesgo de romper `MenuPublicoTest` y `MenuPublicoContextoJsTest`). Y aun así E no podría usarlo tal cual por las reglas de seguridad que pediste. Descartada: obligaría a detenerse y preguntar.

**B. E trae su propio script**, que lee los datos que el servidor renderiza (atributos de datos con escape) y reutiliza las mismas reglas de configuración y el mismo formato de mensaje. No toca `publico.blade.php`, `dish-card.blade.php`, `menu.js` ni el controlador; la salida de A, B, C y D no cambia. El costo es duplicar la armadura del mensaje en JavaScript (unas 30 líneas), que es presentación y no lógica de negocio: las reglas (quién puede pedir, qué platillos hay, precios, número) las decide el servidor.

**Recomendación: B.** Riesgos para A a D en B: ninguno en HTML ni comportamiento. Los únicos archivos compartidos que habría que tocar son aditivos (un método nuevo en `Restaurante` para el número validado, que no cambia lo que ya usa `menu.publico`, y el registro del script en `vite.config.js`).

Detalles de diseño previstos en B, para que no haya sorpresas:

- El carrito de E guarda en `localStorage` solo identificadores, cantidades y notas (clave distinta a la del menú actual). Nombres y precios se vuelven a leer de los atributos de datos de la página en cada carga; una línea cuyo platillo ya no existe o está agotado se descarta.
- El número y las reglas para pedir se calculan en el servidor y el botón no se dibuja si no se cumplen. Sin JavaScript, el menú se ve completo y queda un enlace a WhatsApp sin pedido armado.

### Decisiones pendientes (preguntas para el dueño del proyecto)

1. ¿Se mantiene la condición "abierto ahora" del menú actual para poder pedir?
2. ¿El número de pedidos sale solo de `whatsapp_pedidos`, como en el menú actual, sin el respaldo del contacto WhatsApp?
3. ¿Se conserva el campo Mesa editable con "Para llevar" como en el menú actual?
4. ¿Se conserva la "Nota para la cocina" general?

## Pedido y reservas: lo construido (fase 2)

Decisiones del dueño del proyecto: se mantiene la condición "abierto ahora" (cerrado, E muestra un aviso en lugar del botón), el número sale solo de `whatsapp_pedidos` con 8 a 20 dígitos, la mesa es un campo editable con el valor de `?mesa=` y "Para llevar" si queda vacío, y se incluye la nota para la cocina con límite de largo. Opción B: E trae su propio script.

### Cómo funciona

- **Servidor** (`menu/plantillas/e.blade.php`): `puedePedir = pedidos_activos && estaAbierto && Restaurante::whatsappPedidosDigitos() !== null`. Solo entonces se dibujan los botones "Agregar", la barra "Ver pedido" y el cajón. Sin JavaScript queda el menú completo y el enlace "Pedir por WhatsApp" (`wa.me/{dígitos}` sin pedido armado). Cerrado y con pedidos activos, se muestra "Estamos cerrados ahora...".
- **Datos para el script**: atributos `data-*` con escape de Blade. En cada botón: `data-producto`, `data-variante`, `data-nombre`, `data-nombre-variante`, `data-precio` (decimal del servidor: `precioVigente()` o el precio de la variante). En el cajón: número, restaurante, moneda, idioma (`FormatoMoneda::locale`), mesa (la misma validación del controlador) y clave de `localStorage`.
- **Script** (`resources/js/sitio-cafeteria-pedido.js`): carrito, cajón (`<dialog>` nativo: trampa de foco, Escape y fondo inerte sin código propio) y envío. `localStorage` (`menuonl_cart_e_{slug}`) guarda solo `{p, v, c, n}`: ids, cantidad y nota; en cada carga se contrasta con los platillos de la página y se descarta lo que ya no existe o está agotado. Todo se inserta con `textContent`/nodos; no hay `innerHTML` ni `onclick`.
- **Reglas puras** (`resources/js/sitio-cafeteria-pedido-mensaje.js`, probadas con Node): notas sin caracteres de control ni marcas de WhatsApp (`* _ ~` y comilla invertida), 80 caracteres por nota, 200 para la cocina, mesa de hasta 20 caracteres, cantidad de 1 a 20, 30 líneas como máximo, importes en centavos enteros, enlace de máximo 2000 caracteres: si se excede se omiten platillos del final (con una línea en el mensaje y un aviso al cliente) y, como último recurso, la nota de cocina. El total siempre va completo y se avisa que es informativo.
- **Reservas** (`components/sitio/cafeteria/reservar.blade.php`): mismos ids, campos y `data-*` que `sitio/partes/reservar.blade.php`, por lo que `resources/js/sitio.js` y `ReservaPublicaController` se reutilizan sin cambios (validación, campo trampa, `ts`, límites de tasa, cupo). Solo se dibuja con `reservas_activas`; igual el acceso "Reservar" de la cabecera (`#reservar` en el sitio, `route('sitio.publico').'#reservar'` en el menú).

### Formato del mensaje: debe mantenerse igual al de `menu.publico`

El mensaje de E (`armarEnlace()` en `sitio-cafeteria-pedido-mensaje.js`) replica el de `enviarPedidoWhatsApp()` en `resources/views/menu/publico.blade.php`: saludo, `*Mesa:*`, `*Pedido:*` con `• {n}x {platillo} ({variante}) - {subtotal}` y `_Nota: ..._`, `*Nota para la cocina:*` opcional y `*Total:*`. Si uno cambia, hay que cambiar el otro; el script de E lo dice en un comentario y una prueba (`tests/Js/pedido-mensaje.test.mjs`) fija el formato de E.

### Diferencias deliberadas con `menu.publico` (solo en E)

- Precios y nombres se leen de la página en cada carga, no de `localStorage`.
- Notas limpias y limitadas; el enlace se recorta con aviso; sin `innerHTML`.
- El número debe tener de 8 a 20 dígitos y viene solo de `whatsapp_pedidos`.
- Un platillo agotado con variantes no se puede agregar aunque la variante esté disponible.
- Cerrado: aviso corto sin la próxima apertura.

### Cambios fuera de la plantilla E

1. `app/Models/Restaurante.php`: método nuevo `whatsappPedidosDigitos()` (aditivo; `menu.publico` no lo usa).
2. `vite.config.js`: entrada `resources/js/sitio-cafeteria-pedido.js`.
3. `tests/Feature/SitioCafeteriaTest.php`: el menú se prueba con la hora fija (abierto) y el contenido del sitio con el tiempo congelado, porque el formulario de reservas lleva la hora de renderizado.

No se tocó `frontend/`, ni `publico.blade.php`, `dish-card.blade.php`, `menu.js`, `sitio.js`, `MenuPublicoController`, `SitioPublicoDatos` ni las plantillas A a D.

### Corrección incluida en la hoja de E

`.cal-carrusel__pista { position: relative; }`: el texto solo para lectores de pantalla de las tarjetas escondidas a la derecha del carrusel ensanchaba la página a 584 px en 360 px.
