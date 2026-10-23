# Pedido por WhatsApp compartido: análisis y propuesta (Fase 1)

Estado: **solo análisis. No hay código escrito.** Espera aprobación.

Plantillas: E = `cafeteria` (prefijo `cal`), F = `bar` (`bar`), G = `pastel` (`pas`), H = `pizzeria` (`piz`). `menu.publico` sirve las plantillas a-d.

## 1. Comparación de las copias

### 1.1 E, F, G, H: son la misma copia

Cada plantilla tiene dos archivos en `resources/js/`. Los comparé con `diff` después de normalizar el prefijo.

| Archivo | Líneas | Diferencias reales entre plantillas |
|---|---|---|
| `sitio-{x}-pedido-mensaje.js` | 142 | **Ninguna de código.** Solo cambian 2 líneas de comentario (letra de plantilla y ruta del doc). |
| `sitio-{x}-pedido.js` | 342 | El prefijo de los atributos `data-{p}-*` (`cal`, `bar`, `pas`, `piz`), el `import` y el comentario de la línea 1. |
| Valor por defecto de `clave` | | E: `menuonl_cart_e`. F: `menuonl_cart_f`. H: `menuonl_cart_h`. **G: `menuonl_cart_f`**, copiado de F. No tiene efecto hoy porque el blade siempre manda `data-clave`. |
| `components/sitio/{x}/pedido.blade.php` | 92 | Solo el prefijo, la clave (`menuonl_cart_{e,f,g,h}_{slug}`), algunas clases de CSS (`__boton__icono` frente a `__icono`, `visualmente-oculto` frente a `solo-lectores`, variantes de botón) y que H tiene el botón extra `aun-no`. |
| `@vite` en `layout.blade.php` | | Idéntico salvo el nombre del entry. Cada plantilla carga solo su `sitio-{x}-pedido.js`, y solo si `$pedido` es verdadero. |

**Formato del mensaje, E-H.** Es el mismo código en las cuatro. Estructura exacta:

```
Hola, quiero pedir en *{restaurante}*:\n\n
*Mesa:* {mesa|Para llevar}\n\n
*Pedido:*\n
• {n}x {nombre}[ ({variante})] - {importe}\n
[  _Nota: {nota}_\n]
\n
[*Nota para la cocina:* {cocina}\n\n]
*Total:* {total}
```

Todo se codifica con `encodeURIComponent` y va en `https://wa.me/{numero}?text=`. **No hay diferencias de formato entre E, F, G y H.**

### 1.2 `menu.publico` frente a E-H

La estructura de líneas del mensaje es la misma. Para las mismas entradas ya limpias, el texto sale igual. Lo que difiere es lo que pasa **antes** de armarlo:

| Aspecto | `menu.publico` (a-d) | E-H |
|---|---|---|
| Dónde vive | `<script>` en línea en `publico.blade.php` (líneas 477-850), más `menu.js` | Módulos de Vite, sin script en línea |
| Eventos | `onclick="..."` y funciones en `window` | `addEventListener`, sin `onclick` |
| Render | `innerHTML` (líneas 756 y 807) | `textContent` y nodos del DOM |
| Mesa | `.trim()` y nada más | `limpiarMesa`: solo letras, números, espacio, `-` y `_`; espacios colapsados; máximo 20 |
| Nota de línea | `.trim()` | `limpiarTexto`: sin control, sin `* _ ~ \``, espacios colapsados, máximo 80 |
| Nota de cocina | `.trim()` | `limpiarTexto`, máximo 200 |
| Límite de enlace | Ninguno | 2000 caracteres: omite platillos del final, avisa en el mensaje y, como último recurso, quita la nota de cocina |
| Límites de cantidad | Ninguno | 20 por platillo, 30 líneas |
| Total | `precio * cantidad` en coma flotante, sumado | Centavos enteros; `centavos()` valida `^\d{1,9}(\.\d{1,2})?$` |
| Formato de moneda | `Intl` con código si `$` es ambiguo. En el respaldo usa `escapeHtml(MONEDA)` | Igual, pero el respaldo usa `moneda` tal cual |
| Número | Se limpia a dígitos en el cliente, sin validar largo | Debe cumplir `^[0-9]{8,20}$`, si no hay enlace |
| localStorage | `menuonl_cart_{slug}` con `producto_id`, **`nombre`, `variante_nombre`, `precio`**, cantidad y notas | `menuonl_cart_{e,f,g,h}_{slug}` con `{p, v, c, n}` (solo ids, cantidad y nota) |
| Origen de nombre y precio al enviar | Lo que quedó guardado en el navegador | El catálogo de la página, escrito por el servidor |
| Carrito vacío | Toast "Tu pedido está vacío." | Botón de enviar deshabilitado |

### 1.3 Diferencias de formato: te toca decidir

No las unifiqué. Estas son las que cambian lo que recibe el restaurante:

1. **Notas con marcas de WhatsApp.** Si el cliente escribe `sin *hielo*`, `menu.publico` manda `_Nota: sin *hielo*_` y E-H mandan `_Nota: sin hielo_`.
2. **Mesa con símbolos.** `Mesa #3` o `Terraza (2)` llegan tal cual en `menu.publico`. En E-H salen `Mesa 3` y `Terraza 2`.
3. **Espacios y saltos de línea.** Una nota con varios espacios o saltos conserva los espacios en `menu.publico`. E-H los colapsan.
4. **Pedidos muy largos.** `menu.publico` no recorta el enlace, y WhatsApp o el navegador pueden cortarlo. E-H recortan y avisan.
5. **Total en casos límite.** El total en coma flotante puede diferir en un centavo del total en centavos enteros, en raros casos de redondeo con muchas líneas. No lo verifiqué con datos reales.
6. **Moneda inválida.** Solo difiere si el código de moneda tuviera `& < > " '`. En la práctica es un código ISO.

**¿Alguna diferencia afecta a un pedido normal?** Sí, dos, ambas por entradas que un cliente real puede escribir:

- **Nota para la cocina con varias líneas.** Es un `<textarea>` en `menu.publico`. Hoy el mensaje conserva los saltos de línea; con el módulo compartido se colapsan a un espacio. Es el caso normal más probable.
- **Mesa con símbolos habituales**, como `Barra/2`, `Mesa #3` o `Terraza (2)`. Hoy llegan tal cual; con el módulo salen `Barra2`, `Mesa 3` y `Terraza 2`.

Un pedido normal es de una sola línea y sin símbolos en la mesa, y en ese caso el mensaje queda idéntico. El total en centavos enteros frente a coma flotante no cambia lo que se muestra, porque `Intl` redondea a 2 decimales. Aun así, no lo he verificado con muchos precios reales. Esa comprobación irá en el PR 3.

**Decisión tomada (ver sección 6):** `menu.publico` adopta el formato del módulo compartido, en un PR aparte y al final.

## 2. Propuesta de módulo compartido

### 2.1 Qué va dentro

Carpeta nueva `resources/js/pedido/`. Si prefieres otra ubicación, dímelo, porque `AGENTS`/CLAUDE.md pide aprobación para carpetas base nuevas. Es una subcarpeta dentro de `resources/js`.

- **`pedido/mensaje.js`**. Es el contenido de hoy de `sitio-cafeteria-pedido-mensaje.js`, movido sin cambios de lógica: `LIMITES`, `limpiarTexto`, `limpiarMesa`, `centavos`, `crearFormateador`, `totalizar` y `armarEnlace`. Puro, sin DOM. Incluye validación y límites de entrada, el cálculo del total y la construcción del mensaje y del enlace.
- **`pedido/controlador.js`**, con `iniciarPedido(prefijo)`. Es el contenido de `sitio-{x}-pedido.js` con los selectores construidos desde el prefijo (por ejemplo `` `[data-${prefijo}-dialogo]` ``). Incluye el catálogo leído del DOM, `cargar` y `guardar` de localStorage (`{p, v, c, n}`), el diálogo, las cantidades y los toasts.

### 2.2 Qué queda en cada plantilla

- El marcado (`pedido.blade.php`), las clases y el CSS (`resources/css/sitio/{e,f,g,h}.css`).
- La clave de localStorage (`data-clave`) y el prefijo de atributos.
- Un entry de Vite de una sola línea: `sitio-{x}-pedido.js` pasa a ser `import { iniciarPedido } from './pedido/controlador.js'; iniciarPedido('bar');`.
- Si H necesita algo propio (botón `aun-no`), el controlador ya lo maneja hoy con `hasAttribute`. No hace falta nada especial.

### 2.3 Carga con Vite

- `vite.config.js` y los `@vite(...)` de los layouts **no cambian**. Siguen apuntando a `sitio-{x}-pedido.js`, que se mantiene como entry.
- Rollup extraerá `pedido/*.js` a un chunk compartido. Cada página sigue cargando un solo entry, más ese chunk precargado. Habrá una petición más en caché frente a hoy.
- Tras el cambio hay que correr `npm run build` y revisar `public/build/manifest.json`.

### 2.4 `menu.publico`

Fuera de este trabajo hasta que decidas la sección 1.3. Si luego decides unificarlo, es un trabajo distinto, porque exige reescribir el script en línea y quitar `onclick`/`innerHTML`, y cambiar el formato de localStorage. Eso hace que los carritos guardados de los clientes se pierdan una vez.

## 3. Riesgos

| Riesgo | Detalle | Mitigación |
|---|---|---|
| **Las pruebas actuales leen los archivos por ruta.** | `PedidoWhatsapp{Bar,Cafeteria,Pastel,Pizzeria}Test::test_el_script_no_usa_innerhtml_ni_manejadores_ni_guarda_precios` hace `file_get_contents` de `sitio-{x}-pedido.js` y `sitio-{x}-pedido-mensaje.js`, y afirma: `encodeURIComponent` y `menu.publico` en el `-mensaje.js`, `menu.publico` y `{ p: producto, v: variante \|\| null, c: linea.cantidad, n: linea.nota }` en el `-pedido.js`. | Ver la decisión A abajo. Con archivos delgados, esas afirmaciones **fallarían**. |
| Los tests de JS importan `sitio-{x}-pedido-mensaje.js`. | Si borro esos archivos se rompen. | Conservarlos como `export * from './pedido/mensaje.js'`. Los tests no cambian. |
| Cambio de bytes por descuido al mover código. | El requisito es byte a byte. | Antes de mover nada, capturar la salida (URL completa) de `armarEnlace` con un conjunto fijo de entradas, y comparar después. Los tests de formato existentes ya cubren la estructura. |
| Selectores por prefijo mal construidos. | Un `data-*` mal escrito rompe una plantilla en silencio. | Migrar de una en una y verificar en el navegador (Playwright) abrir, agregar, enviar y vaciar. |
| Chunk compartido y caché. | El nombre con hash cambia. | `npm run build` en cada paso, y comprobar el manifest. |
| `docs/plantilla-*.md` y comentarios. | Mencionan los archivos actuales. | Actualizarlos solo en el paso final, y solo lo que esté desactualizado. |

### Decisión A (necesito tu respuesta)

Para que las pruebas PHP sigan pasando **sin tocarlas**, los archivos por plantilla tendrían que seguir conteniendo esos textos literales. Eso obliga a dejar duplicados en cada plantilla el código de `encodeURIComponent` y el de `{ p: producto, ... }`, y entonces no hay unificación real. Opciones:

1. **Recomendada:** cambiar esas pruebas para que lean los archivos compartidos (`pedido/mensaje.js` y `pedido/controlador.js`) además del entry. No cambian expectativas de formato, solo la ruta de los archivos que inspeccionan. Lo hago solo si lo apruebas, porque tu regla dice que pare y pregunte.
2. Dejar esos textos como comentarios en los archivos delgados para satisfacer las pruebas. Es un truco que oculta la intención y no lo recomiendo.
3. Unificar solo `mensaje.js` y dejar los `sitio-{x}-pedido.js` completos. Menos riesgo, pero deja 4 copias de 342 líneas.

## 4. Orden de migración en pasos pequeños

Cada paso es un PR independiente. Antes de cada uno corro la suite completa (`php artisan test`, incluye `PedidoWhatsappJsTest`, que ejecuta `node --test tests/Js/*.test.mjs`), Pint y `npm run build`.

1. **Crear `pedido/mensaje.js`** como copia exacta del de E. Convertir los cuatro `sitio-{x}-pedido-mensaje.js` en `export * from '../pedido/mensaje.js'`, solo si resuelves la decisión A. Sin cambios en blades ni Vite.
2. **Añadir prueba de equivalencia** (JS, sin ids fijos ni valores que excedan columnas) con salida de URL esperada fija. Se agrega antes de mover más código, para tener la red de seguridad.
3. **Crear `pedido/controlador.js`** y migrar **E** (`cafeteria`). Verificación manual en el navegador.
4. Migrar **F** (`bar`).
5. Migrar **G** (`pastel`). Corregir de paso el valor por defecto de `clave` solo si lo apruebas. Es inocuo, pero cambia un literal.
6. Migrar **H** (`pizzeria`), incluyendo `aun-no`.
7. **Limpieza:** actualizar comentarios y `docs/plantilla-*.md`.
8. **`menu.publico`**: decisión tuya después de leer la sección 1.3. Es una fase aparte.

## 5. Qué necesito de ti

1. Decisión A (1, 2 o 3).
2. ¿Aprobada la ubicación `resources/js/pedido/`?
3. ¿Quieres corregir el `clave` por defecto de G (paso 5) o dejarlo?
4. De la sección 1.3: ¿cuál formato es el correcto para `menu.publico`, o lo dejamos fuera?

## 6. Decisiones aprobadas y plan vigente (tres PR)

Decisiones: (1) las pruebas PHP pasan a leer los módulos compartidos, sin tocar ninguna expectativa de formato; (2) carpeta `resources/js/pedido/`; (3) la clave por defecto de G pasa a `menuonl_cart_g` y se añade una prueba de que cada plantilla manda `data-clave`, sin cambiar las claves en uso (`menuonl_cart_{e,f,g,h}_{slug}`); (4) `menu.publico` va al final y adopta el formato del módulo.

1. **PR 1: módulo + prueba de equivalencia (hecho en el árbol de trabajo, sin commit).**
   - `resources/js/pedido/mensaje.js`: copia de la lógica de E. Nadie lo importa todavía.
   - `tests/Js/fixtures/pedido-mensaje-referencia.json`: resultados de referencia generados con el código ACTUAL de E, F, G y H, que coincidían entre sí en todo. Incluye 16 casos de mensaje (normales, hostiles, largos, número inválido, moneda inválida), tablas de `limpiarTexto`, `limpiarMesa`, `centavos` y del formato de moneda.
   - `tests/Js/pedido-compartido-equivalencia.test.mjs`: ejecuta la referencia contra el módulo compartido y contra los cuatro módulos actuales. Es la red de seguridad de los PR 2 y 3.
2. **PR 2: migrar E, F, G y H.** Los `-mensaje.js` pasan a re-exportar el módulo; se crea `pedido/controlador.js`; migración de una plantilla por commit (E, F, G, H); se corrige la clave de G; se cambian las rutas en las cuatro pruebas PHP de `PedidoWhatsapp*`; se añade la prueba de `data-clave`.
3. **PR 3: `menu.publico`.** Reescribir su script con el módulo, sin `onclick` ni `innerHTML`, con migración del carrito guardado (`menuonl_cart_{slug}`) o descarte controlado. Ver las dos diferencias de 1.3.

## 7. Decisión sobre 1.3 y estado del PR 2

### Decisiones sobre 1.3

1. **Nota para la cocina con varias líneas:** se queda como en el módulo (saltos y espacios colapsados a un espacio). Sin cambios.
2. **Mesa (`limpiarMesa`):** los caracteres no permitidos se **reemplazan por un espacio** en lugar de borrarse; después se colapsan los espacios y se recorta. Mismo conjunto permitido (letras, números, espacio, `-`, `_`) y mismo límite de 20 caracteres.

### Qué entradas cambian respecto a hoy en E, F, G y H

Solo las entradas con **algún carácter fuera del conjunto permitido** cambian. Se comprobó de tres maneras: (a) las 16 105 cadenas generadas solo con caracteres permitidos (letras con y sin acento, dígitos, espacios, `_`, `-`, hasta 4 de largo) dan el mismo resultado que hoy; (b) el script que regeneró la referencia falla si cambia una entrada sin símbolos; (c) los casos de mensaje cambian solo en la línea `*Mesa:*`.

| Entrada de mesa | Hoy (E-H) | Nuevo |
|---|---|---|
| `Barra/2` | `Barra2` | `Barra 2` |
| `Mesa(3)` | `Mesa3` | `Mesa 3` |
| `A.B` | `AB` | `A B` |
| `Ñandú/1` | `Ñandú1` | `Ñandú 1` |
| `5°piso` | `5piso` | `5 piso` |
| `a%0Ab&c=d` | `a0Abcd` | `a 0Ab c d` |
| `<b>Mesa 3</b>` | `bMesa 3b` | `b Mesa 3 b` |
| `Mesa<TAB>4` | `Mesa4` | `Mesa 4` |
| `a<salto de línea>b` | `ab` | `a b` |
| `xxxxxxxxxxxxxxxxxxx/y` (19 x) | `xxxxxxxxxxxxxxxxxxxy` | `xxxxxxxxxxxxxxxxxxx` (el límite de 20 corta antes de la `y`) |

No cambian: `Mesa #3` (`Mesa 3`) y `Terraza (2)` (`Terraza 2`), porque ya tenían un espacio junto al símbolo y por eso hoy salen igual. Tampoco cambian `3`, `A-1`, `Terraza 2`, `Ñandú_1-b`, `***` (vacío, sale `Para llevar`) ni las mesas de más de 20 caracteres sin símbolos.

Efecto en `menu.publico` (PR 3): `Mesa #3` y `Terraza (2)` pasan de llegar tal cual a `Mesa 3` y `Terraza 2`; `Barra/2` a `Barra 2`.

Se actualizaron la referencia (`tests/Js/fixtures/pedido-mensaje-referencia.json`: tabla de `limpiarMesa` y los casos de mensaje, más tres casos nuevos con `Barra/2`, `Mesa #3` y `Terraza (2)`) y la expectativa de `limpiarMesa('<b>Mesa 3</b>')` en las cuatro pruebas `tests/Js/pedido-mensaje*.test.mjs`.

### PR 2 (migración de E, F, G y H): hecho en el árbol de trabajo, sin commit

- `resources/js/pedido/controlador.js` con `iniciarPedido(prefijo, claveDefecto)`. Se comprobó mecánicamente que, expandido con el prefijo de cada plantilla, es idéntico línea por línea al `sitio-{x}-pedido.js` anterior (salvo el envoltorio y la clave por defecto).
- `sitio-{x}-pedido.js`: dos líneas, `iniciarPedido('{prefijo}', 'menuonl_cart_{e|f|g|h}')`.
- `sitio-{x}-pedido-mensaje.js`: `export * from './pedido/mensaje.js'`.
- Clave por defecto de G: `menuonl_cart_g` (antes copiaba `menuonl_cart_f`). Las claves en uso (`data-clave="menuonl_cart_{e,f,g,h}_{slug}"`) no cambian; ya las fijaba cada prueba `PedidoWhatsapp*`, y ahora cada prueba también fija la clave por defecto del entry.
- Pruebas PHP `PedidoWhatsapp*`: solo cambian las rutas que inspeccionan (controlador y mensaje compartidos, más el entry).
- Vite: sin cambios en `vite.config.js` ni en los layouts. El build genera un chunk `controlador` compartido por los cuatro entries.

## 8. PR 3, Fase 1: inventario y plan para `menu.publico` (sin código)

Estado: **solo análisis, espera aprobación.** La rama está limpia; los PR 1 y 2 ya están integrados (`pedido/mensaje.js` y `pedido/controlador.js` existen y los usan e, f, g y h).

### 8.1 Inventario del flujo actual

**Elementos** (`resources/views/menu/publico.blade.php` y `menu/partials/dish-card.blade.php`):

| Elemento | Detalle |
|---|---|
| `#cartToast` / `#cartToastMessage` | Toast; se muestra con la clase `visible` 2,5 s. Siempre en el HTML. |
| `#floatingCartBar` | Botón fijo, `style="display: none;"`, `onclick="openCartModal()"`. Contiene `#cartCountBadge` y `#cartTotalSummary`. Solo existe si `$puedePedir`. |
| `#cartModalBackdrop` > `.cart-drawer` | Fondo con `onclick="closeCartModal(event)"` y `@hoja-cerrar="closeCartModal(null, true)"` (Alpine); el cajón lleva `onclick="event.stopPropagation()"` y `x-data="hoja"` (arrastre para cerrar). Se abre con la clase `open` y se cierra con `cerrando` (200 ms). Solo si `$puedePedir`. |
| Dentro del cajón | `#btnVaciarCarrito`, `#btnCloseCartModal`, `#vaciarConfirmBanner` (Conservar / Vaciar pedido), `#pedidoEnviadoBanner` (Ya lo mandé / Todavía no), `#cartItemsContainer`, `#inputMesa`, `#inputNotasGenerales`, `#modalCartTotal`, `#btnEnviarWhatsApp`. |
| Botones "Agregar" (`.btn-cart-add`) | Uno por platillo sin variantes (`.plato-pie`) y uno por variante disponible. `onclick="cartAddItem({producto_id, variante_id, nombre, variante_nombre, precio})"`, con los textos como literales `@js(...)`. Solo se dibujan si `$puedePedir`; si el platillo o la variante están agotados, sale un botón deshabilitado o la etiqueta "Agotado". |
| Hoja de detalle del plato (`#platoHojaFondo`) | `menu.js` **clona** (`cloneNode`) `.plato-pie` y `.plato-variantes` dentro de la hoja, así que hoy el `onclick` viaja en el clon. `detallePlato.clic` cierra la hoja 150 ms después de pulsar cualquier `.btn-cart-add`. |

**Script en línea** (líneas 477-849). Funciones en `window`: `cartAddItem`, `cartUpdateQty`, `cartUpdateItemNotes`, `cartConfirmarVaciar`, `cartCancelarVaciar`, `cartEjecutarVaciar`, `openCartModal`, `closeCartModal`, `enviarPedidoWhatsApp`, `cartPedidoEnviado` y `mostrarNotificacionCarrito` (esta no la usa nadie). Listeners en `document`: `keydown` (trampa de Tab y Escape) y dos `DOMContentLoaded`. **El segundo `DOMContentLoaded` no es del pedido**: es el `IntersectionObserver` que marca la píldora activa de la navegación de categorías. Hay que moverlo, no perderlo.

**Acoplamiento con `menu.js`:** tres lugares llaman a `window.closeCartModal(null, true)` (`hoja.cerrarArrastrado`, `hoja.closeCartModal` y `cabecera.closeCartModal`, esta última sin uso real).

**Datos que entrega el servidor:** constantes en el script en línea: `NOMBRE`, `CART_KEY` (`menuonl_cart_{slug}`), `MONEDA`, `WHATSAPP_NUM` (solo dígitos, **sin validar largo**), `PUEDE_PEDIR` e `IDIOMA` (locale). Por botón: ids, nombre, variante y precio en el `onclick`. `?mesa=` llega filtrada por el controlador (`/\A[A-Za-z0-9 _-]{1,20}\z/`) y se escribe en el `value` de `#inputMesa` y en la insignia "Mesa X".

**localStorage:** clave `menuonl_cart_{slug}`. Valor: arreglo JSON de `{producto_id, variante_id|null, nombre, variante_nombre|null, precio (número), cantidad, notas}`. Se escribe en cada cambio; las notas, en cada tecla, sin repintar. El nombre y el precio que se envían son los guardados en el navegador (editables por el cliente).

**Cómo se decide cada estado:**
- `$puedePedir = pedidos_activos && estaAbierto && filled(whatsapp_pedidos)`. Si es falso no hay barra, cajón ni botones, y un aviso explica por qué (pedidos desactivados, cerrado con próxima apertura, o sin número).
- Agotado: botón deshabilitado "Agotado" (sin variantes) o etiqueta "Agotado" (variante). Hoy una línea ya guardada de un platillo que luego se agotó **sigue en el carrito**.
- `?mesa=`: rellena el campo. "Para llevar" no es un interruptor: es lo que sale si el campo queda vacío tras `trim()`.
- Carrito vacío: el botón de enviar queda deshabilitado; el toast "Tu pedido está vacío." casi no se alcanza.

### 8.2 Plan de migración

**Datos desde el servidor, sin script en línea.** Se añade un contenedor `<div data-mo-pedido data-numero data-restaurante data-moneda data-locale data-mesa data-clave>` (solo si `$puedePedir`) que envuelve toast, barra y cajón. Los tres son `position: fixed`, así que el envoltorio no cambia el aspecto. Los botones "Agregar" pasan de `onclick` a `data-mo-agregar data-producto data-variante data-nombre data-nombre-variante data-precio`; Blade escapa los atributos. Los clones de la hoja de detalle conservan los `data-*`, por lo que la delegación en `document` los atiende sin cambios. El número sale de `whatsappPedidosDigitos()` (8 a 20 dígitos), igual que en e-h.

**DOM sin `innerHTML` ni `onclick`.** Las líneas del pedido salen de un `<template data-mo-plantilla-linea>` con **las mismas clases de hoy** (`py-3 border-b divisor`, `btn-cant`, `campo`...), rellenado con `textContent`. Los eventos se delegan en el cajón (clic, `input`) y en `document` (agregar).

**El módulo compartido no sirve tal cual** (decisión A). `controlador.js` está hecho para un `<dialog>` nativo (`showModal`), toast con `hidden` y botones "Agregar" ocultos hasta que carga el JS. `menu.publico` usa fondo + cajón con clases `open`/`cerrando`, arrastre de Alpine, trampa de foco propia y bloqueo del scroll del `body`. Cambiar eso alteraría el aspecto en los cuatro estilos. Propuesta:
- `iniciarPedido(prefijo, claveDefecto, opciones = {})` con un tercer parámetro **opcional** (`abrir`, `cerrar`, `estaAbierto`, `mostrarToast` y el texto del aviso de "agregado"). Sin `opciones`, el camino de e-h es el de hoy, línea por línea.
- Una ranura opcional `[data-mo-variante]` en la fila: si existe, `(variante)` va ahí con el tamaño menor de hoy; si no (e-h), todo sigue igual.
- `resources/js/pedido/presentacion-hoja.js` (nuevo): abrir y cerrar con `open`/`cerrando`, `prefers-reduced-motion`, Tab y Escape, bloqueo del scroll. Escucha el evento `hoja-cerrar` que ya dispara Alpine, así desaparece `window.closeCartModal` (se quitan sus 3 referencias en `menu.js` y el `@hoja-cerrar` del fondo).
- Entry nuevo `resources/js/menu-pedido.js`: `iniciarPedido('mo', 'menuonl_cart_m', {...})`.

**Vite.** Se añade el entry a `vite.config.js`. Hoy el layout hace `@vite(['resources/css/app.css', $js])` con un solo `js`; añadiría una propiedad opcional `pedidoJs` para cargarlo **solo cuando `$puedePedir`**, como hacen e-h. `menu.js` sigue cargándose siempre y recibe el observador de píldoras. Al terminar, `npm run build` y revisión del manifest.

**Marcado y clases que se conservan.** Todos los `id` (`floatingCartBar`, `cartModalBackdrop`, `cartToast`, `vaciarConfirmBanner`, `btnEnviarWhatsApp`...), clases, textos, `aria-*`, `x-data="hoja"` y la estructura del cajón. Solo cambian atributos de comportamiento (`onclick`/`oninput`/`onchange` por `data-mo-*`) y se quita `style="display: none;"` de la barra, que se sustituye por `hidden`. Eso funciona sobre el `display:flex` de `.floating-cart-bar` porque el CSS compilado de Tailwind trae la regla `[hidden]{display:none!important}` (la encontré en `public/build/assets/app-*.css`). Los cuatro estilos son solo `data-tema` sobre el mismo marcado y el CSS de `public/css/menuonl.css`, que no se toca.

### 8.3 localStorage

- **Clave nueva:** `menuonl_cart_m_{slug}`. Formato `{p, v, c, n}` (ids, cantidad y nota), idéntico al de e-h. Nombres y precios salen siempre del catálogo de la página (los atributos del servidor).
- **Choque de claves:** las de e-h son `menuonl_cart_{e,f,g,h}_{slug}` y la antigua es `menuonl_cart_{slug}`. Con la `m` intermedia, la nueva no coincide con ninguna salvo que un slug empiece por `m_`, `e_`... (el slug lo escribe el dueño y no hay regla que lo impida; es el mismo riesgo que ya existe hoy entre e-h y no cambia).
- **Carrito en formato antiguo:** al iniciar, si no existe la clave nueva y sí la antigua, una función pura (`pedido/migracion.js`, probable con Node) toma solo `producto_id`, `variante_id`, `cantidad` y `notas`, **ignora `nombre` y `precio`**, y las pasa a `{p, v, c, n}`. El controlador ya valida cada línea contra el catálogo (id existente, no agotado, cantidad entera ≥ 1 con tope 20, nota limpia, máximo 30 líneas) y guarda. Después se borra la clave antigua. Si el JSON está corrupto o no es un arreglo, se descarta y se borra, sin error. Una línea cuyo platillo ya no existe o está agotado se descarta.

### 8.4 Dónde cambia el comportamiento para un pedido normal

El contenido y la estructura del mensaje son los mismos (ya fijados por la referencia del PR 1). Cambios reales, de menor a mayor:

1. **Total en centavos frente a coma flotante: sin diferencia visible.** Comparé `Intl.NumberFormat('es-MX', MXN)` de la suma flotante con la de centavos en 485 720 combinaciones (precios de 0,01 a 999,99 con paso de 7 centavos por cantidades 1-20, más 200 000 pedidos aleatorios de 1 a 30 líneas): **0 diferencias**. Ejemplo: 3 × 19,99 da `$59.97` en ambos, aunque en coma flotante el valor interno sea 59.97000000000001.
2. **Texto del aviso al agregar.** Hoy: `Taco al pastor (Grande) agregado`. El módulo: `... agregado al pedido.` Es un texto visible, no del mensaje. Propongo conservar el de hoy con la opción (decisión D).
3. **Precios guardados que cambiaron.** Hoy un carrito guardado conserva el precio viejo. Nuevo: se usa el precio vigente de la página. Es una corrección, pero un cliente con un carrito de ayer puede ver un total distinto.
4. **Platillos agotados u ocultos** desde que se guardó el carrito: hoy siguen; nuevo: se descartan.
5. **Número de pedidos no vacío pero inválido** (por ejemplo `123`): hoy se muestra el carrito y abre un `wa.me/123` roto; con `whatsappPedidosDigitos()` se trata como "sin número" (aviso "Por ahora no recibimos pedidos aquí"). Es el criterio de e-h (decisión C).
6. **Límites nuevos:** 20 por platillo (el `+` se deshabilita y sale un aviso), 30 líneas, nota de línea 80, nota de cocina 200 y mesa 20 caracteres (el campo se limpia al enviar).
7. `aria-disabled` en el botón de enviar: hoy se escribe además de `disabled`; el módulo solo usa `disabled`. Sin efecto práctico.
8. Entradas hostiles o raras: ya decididas en la sección 7 (mesa con símbolos a espacio, saltos de línea de las notas a espacio, sin `* _ ~` en las notas).

### 8.5 Pruebas que leen `menu.publico` por su contenido

Las expectativas de **formato de mensaje no cambian**; lo que cambia es dónde se verifican, porque el HTML ya no contiene el script. No hace falta detenerse por formato. Cada prueba:

| Prueba | Qué lee hoy | Qué cambia |
|---|---|---|
| `MenuPublicoContextoJsTest::test_el_script_del_carrito_pasa_los_datos_como_literales_js_escapados` (varios casos) | Bloque `<script>` con `CART_KEY` y literales `Js::from` | Reescribir: sin script en línea; `data-restaurante`, `data-clave`, `data-moneda` y `data-numero` salen escapados y decodifican al valor original. |
| `::test_un_apostrofo_no_sale_como_entidad_html_dentro_del_script` | `Tony&#039;s` ausente del script | Reescribir: el atributo lleva entidades HTML válidas y decodifica a `Tony's "Special"`. |
| `::test_plantilla_de_mensaje_whatsapp_en_script_usa_textos_aprobados_del_lote_1` | Plantillas `${NOMBRE}`, `${mesaText}`... en el script | Se verifica en `pedido/mensaje.js` (mismos textos aprobados y las negativas de textos obsoletos) y con la referencia de `tests/Js`. |
| `::test_los_onclick_de_agregar_al_carrito_no_se_rompen_con_nombres_hostiles` | `onclick="cartAddItem(...)"` ×2 | Reescribir sobre `data-mo-agregar`: dos botones, valores íntegros tras decodificar, sin rastro crudo. |
| `::test_matriz_hostil_xss_en_onclick_y_atributos_dish_card` (6 casos) | `onclick` ×2 y `Js::from` en el HTML | Igual, sobre atributos `data-*`; se conservan las negativas (`onmouseover=`, `onfocus=`, `<script>alert(`) y la de `aria-label`. |
| `AdversarialPublicMenuTest` (≈1140-1160, tamaños táctiles) | `cartEjecutarVaciar()` y `cartCancelarVaciar()` en el HTML | Sustituir por `data-mo-confirmar` y `data-mo-conservar`; se conservan `btnCloseCartModal`, `btnEnviarWhatsApp` y `min-h-[48px]`. |
| `AdversarialPublicMenuTest` (≈1178-1195, ARIA y foco) | `e.key === 'Escape' \|\| e.key === 'Esc'`, `closeCartModal()`, `closeBtn.focus()` y `bar.focus()` en el HTML | Las afirmaciones HTML de ARIA siguen igual; las de código pasan a leer `pedido/presentacion-hoja.js`. |
| `AdversarialPublicMenuTest::test_notes_input_and_dish_notes_escaping_in_cart_and_whatsapp` | `function escapeHtml(str)`, `replace(/'/g…)` y `encodeURIComponent(msg)` | Pasa a: ningún `innerHTML` en los módulos y `encodeURIComponent` en `pedido/mensaje.js`. `escapeHtml` desaparece a propósito. |
| `::test_restaurant_name_template_literal_xss_in_script_tag` | `Tacos ${alert(1)}` en el contenido | Sigue pasando (aparece en `data-restaurante`); se refuerza con que no haya script en línea del pedido. |
| `::test_restaurant_name_backtick_injection_breaks_template_literal` | `const NOMBRE = Js::from(...)` en el script | Reescribir: el nombre con comilla invertida aparece solo dentro de un atributo escapado. |
| `::test_dish_name_with_newline_creates_unbroken_or_broken_js_string` | `nombre: Js::from("Taco\nAl Pastor")` | Reescribir: `data-nombre` conserva el salto de línea como dato (decodificado). |
| `MenuPublicoTest` (≈640-668, `?mesa=` y número) | `menuonl_cart_{slug}`, `https://wa.me/` y `525512345678` en el HTML | Cambia a `data-clave="menuonl_cart_m_{slug}"` y `data-numero="525512345678"`; `wa.me` se verifica en `pedido/mensaje.js`. Se conserva `value="7"`. |
| `UxUiAccesibilidadTest` (≈247, criterios WCAG) y (≈1244, hoja del carrito) | `e.key === 'Escape' \|\| e.key === 'Esc'` en el HTML | La cadena pasa a buscarse en `pedido/presentacion-hoja.js`; el resto (ids, ARIA, `x-data="hoja"`, textos) no cambia. |
| `PedidoWhatsapp{Bar,Cafeteria,Pastel,Pizzeria}Test::test_las_otras_plantillas_no_cargan_nada_de_*` (4) | `assertSee('cartAddItem')` y `menuonl_cart_otro-{x}` en `menu.publico` de a-d | Una línea por prueba: `data-mo-agregar` y `data-clave="menuonl_cart_m_otro-{x}"`. No toca e-h; solo lo que esas pruebas esperan de a-d. |

No cambian: las pruebas de `floatingCartBar` y `cartModalBackdrop` presentes o ausentes (los ids se conservan), `test_solo_el_menu_publico_carga_alpine…`, la CSP (`default-src 'self' 'unsafe-inline' https:`) y las de caché. Las pruebas `tests/Js` de e-h no se tocan; se añaden la prueba de la migración y la referencia generada con el código actual.

### 8.6 Riesgos y CSP

| Riesgo | Mitigación |
|---|---|
| Tocar el controlador compartido puede romper e-h. | Parámetro opcional que no altera el camino por defecto; la referencia de `tests/Js`, las cuatro `PedidoWhatsapp*Test` y una comprobación en el navegador (E y H) antes de cerrar. |
| La hoja de detalle clona los botones. | Delegación en `document` con `data-*`; prueba en el navegador de agregar desde la hoja. |
| Un platillo recomendado aparece en "Recomendados" y en su categoría. | El catálogo es un `Map` por `producto:variante`; ya es así en e-h. |
| Se pierde el observador de píldoras al borrar el script. | Pasa a `menu.js`; se prueba que `aria-current` sigue marcándose. |
| Foco y trampa de Tab/Escape. | Se trasladan al módulo de presentación con las mismas reglas, incluida la restauración del foco a la barra. |
| Perder la equivalencia por descuido. | **Primer paso de la Fase 2, antes de tocar nada:** ejecutar el `enviarPedidoWhatsApp` ACTUAL (en Node con `vm` y en el navegador) con carritos fijos y guardar las URL como referencia. |

**CSP: no puedo confirmar "ningún script ni estilo en línea" en toda la página, y prefiero decirlo ahora.** La política vigente es `default-src 'self' 'unsafe-inline' https:` (permite lo en línea; no hay `unsafe-eval` y Alpine ya usa su compilación CSP). Tras este PR el **pedido** no deja ningún script, `onclick`, `oninput` ni `onchange` en línea, ni el `style="display: none;"` de la barra. Pero siguen en la página, y no son del pedido:
- el `<script>` de apariencia de `components/layout/publico.blade.php` (compartido);
- `style="--mo-acento…"` en el `body`, `style="--i: n"` en cada tarjeta y `style="font-family: var(--mo-fuente-titulo)"` en el título del cajón;
- `:style` de Alpine (lo aplica el motor CSP, no es un atributo del servidor).

Quitar el estilo del título del cajón exige una clase nueva en `public/css/menuonl.css` (sin cambio visual); lo dejo fuera salvo que lo apruebes. Endurecer la CSP (quitar `'unsafe-inline'`) es un trabajo aparte que requiere resolver esos puntos y usar `nonce`.

### 8.7 Decisiones que necesito

- **A.** ¿Extender `iniciarPedido` con el parámetro opcional de presentación (recomendado) o crear un segundo controlador que comparta `mensaje.js` (duplica ~250 líneas y deja dos lógicas de carrito)?
- **B.** ¿Clave nueva `menuonl_cart_m_{slug}` con migración desde la antigua (recomendado)?
- **C.** ¿Un número no vacío pero inválido se trata como "sin número" (criterio de e-h), cambiando el estado que hoy muestra un carrito con enlace roto?
- **D.** ¿Conservo el aviso de hoy "…agregado" (recomendado, para no cambiar textos visibles) o adopto el del módulo "…agregado al pedido."?
- **E.** ¿Dejo el `style` del título del cajón y los demás estilos listados en 8.6 (recomendado, alcance acotado) o añado la clase al CSS?

## 9. PR 3, Fase 2: implementado (sin commit)

Decisiones aprobadas: A (parámetro opcional de presentación), B (`menuonl_cart_m_{slug}` con migración), C (número inválido = sin número), D (se conserva el aviso "…agregado"), E (los estilos en línea restantes quedan fuera).

- **Referencia:** `tests/Js/fixtures/menu-publico-referencia.json`, generada ejecutando el script en línea ANTERIOR de `menu.publico` antes de tocar nada. Ocho pedidos normales deben dar la misma URL y el mismo total; cuatro entradas raras guardan lo que producían antes y cambian a propósito.
- **Módulos:** `pedido/controlador.js` (tercer parámetro opcional; sin él, E-H se comportan igual), `pedido/presentacion-hoja.js` (cajón: `open`/`cerrando`, Tab, Escape, scroll, `hoja-cerrar`), `pedido/migracion.js`, entry `menu-pedido.js` (solo se carga si `$puedePedir`) y `navegacion-categorias.js` (el observador de píldoras, movido de la vista).
- **Pruebas:** `tests/Js/menu-publico-pedido.test.mjs` (con un DOM mínimo en `tests/Js/ayudas/`), `tests/Js/navegacion-categorias.test.mjs` y `tests/Feature/PedidoMenuPublicoTest.php`; las 14 expectativas PHP se reemplazaron por su equivalente en el mecanismo nuevo.

### Tarea aparte: estilos y scripts en línea que siguen en la página

No se afirma que `menu.publico` quede sin script ni estilo en línea. Siguen, y no son del pedido: el `<script>` de apariencia de `components/layout/publico.blade.php`, `style="--mo-acento…"` en el `body`, `style="--i: n"` en cada tarjeta, `style="font-family: var(--mo-fuente-titulo)"` en el título del cajón y el `:style` de Alpine. Endurecer la CSP (quitar `'unsafe-inline'`) queda como trabajo separado.
