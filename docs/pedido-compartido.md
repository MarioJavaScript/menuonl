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
