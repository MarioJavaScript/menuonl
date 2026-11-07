# Landing nueva de Menubly: plan y estado

Estado: **implementada, sin commit.** Las secciones 0 y 4 traen lo que se decidió y se construyó. El resto conserva el análisis de la Fase 1 y, si contradice a esas dos, mandan ellas.

- Prototipo: `C:\Users\thexl\Desktop\proyectos\landing-menubly\menubly-landing.html`.
- **No publicar hasta escribir `/privacidad` y `/terminos`**, que siguen con `[PÁGINA POR ESCRIBIR]` y están enlazadas desde el pie.

---

## 0. Decisiones tomadas

| # | Tema | Decisión | Dónde queda |
|---|---|---|---|
| **D1** | NFC | Sin confirmar. `NFC_DISPONIBLE` (por omisión `false`): apagada, ninguna mención en HTML, SEO, canvas, planes ni preguntas; encendida, textos de QR + NFC. | `config('menuonl.nfc_disponible')`, `inicio.blade.php`, atributos del canvas |
| **D2** | Fotos | Foto de platillo en los dos planes. "Galería de fotos del restaurante" solo en Aura. | Tarjetas de planes |
| **D3** | Sitio | "Sitio web con plantilla" y "sitio web del restaurante". Nunca "propio" ni dominio. | Tarjetas de planes |
| **D4** | Movimiento | Sin bucles de CSS. La escena se mueve con el scroll, frena y se detiene a los 5 s sin scroll (sin pedir más cuadros). Con `prefers-reduced-motion`, todo estático. La escena mide 300vh (antes 420vh). | `landing.js`, `landing/recorrido.js` |
| **D5** | `three` | Versión exacta `0.187.0`. `public/build` se compila para probar y queda sin commit. | `package.json` |
| **D6** | Celulares | Se decide por capacidad, nunca por ancho de pantalla. Ver sección 4. | `landing-criterio.js`, `landing/rendimiento.js` |
| **D7** | Precio | Lo que diga `config/menuonl.php`. Hoy Lite $199 y Aura $399. | Config |

---

## 1. Qué se conserva de la landing actual

El prototipo no trae pie de página, preguntas frecuentes, menú de ejemplo, enlace al panel ni enlaces legales. Todo eso se conserva.

| Elemento | Origen (nunca escrito a mano) | Se conserva así |
|---|---|---|
| Precios de Lite y Aura | `config('menuonl.precios.lite')` y `.aura` | Tarjetas y pregunta frecuente, con formato `$199 al mes`. El prototipo los parte en `$199<small>MXN/mes</small>`; eso rompería la prueba actual. |
| Nota de precios | `config('menuonl.precios.nota')` | Debajo de los planes, solo si tiene texto. |
| WhatsApp | `SOPORTE_WHATSAPP` → `config('menuonl.soporte_whatsapp')` → `ContactoSoporte::enlaceWhatsapp()` | Mismos mensajes, `rel="noopener noreferrer"`, sin `target="_blank"`. Sin número válido no se pinta ningún botón. No abrí `.env`. |
| Menú de ejemplo | `$demoUrl` de `PortadaController`, con su validación | "Ver un menú de ejemplo" en el hero y el cierre, solo si existe. |
| Privacidad y términos | `route('privacidad')`, `route('terminos')` | En el pie, sin cambios. |
| Entrar al panel | `url('/admin')`, que lleva a `filament.admin.auth.login` | "Entrar" en el pie, tal como exige la prueba. |
| Portada | `route('portada')` y `PortadaController` | Misma ruta, misma caché pública (300 s + 600 s), sin cookies ni sesión. |
| Marca | `config('app.name')` | En títulos, textos y `alt`. |
| Secciones | `como-funciona`, `incluye`, `planes`, `preguntas` | Mismos ids. "Beneficios" apunta a `#incluye`. Se añade `#contacto` en el cierre. |
| Preguntas frecuentes y cierre | Vista actual | Se conservan, con NFC y fotos ajustados según D1 y D2. Incluyen "48 horas", "sin contrato" y "sin pago en línea". |

**Se quita del prototipo:**

- `menubly.2a2gcompany.com` escrito a mano como botón.
- La etiqueta "AURA · TODO INCLUIDO" (no incluye pagos ni etiquetas físicas) y "Todos los beneficios de Menubly" (vago).
- "Cambia precios al instante" y "En un segundo": el menú público tiene caché (`max-age=30`, `stale-while-revalidate=86400`). Se usa el texto actual: "se actualiza".
- "Mesa 7" dentro de la pantalla simulada: ya no hay pedidos de mesa (`docs/planes.md`).

**Además:**

- Los platillos y precios de la pantalla simulada ("Taquería El Güero", `$120`, etc.) son ilustrativos y no son precios de planes, así que pueden seguir en el JS. El HTML nunca los incluye.
- El layout, `cabecera`, `pie` y `landing.css` **también los usan `/privacidad` y `/terminos`**. "Reemplazar los estilos" significa conservar tokens, base, cabecera, pie, botones y estilos legales, y quitar solo lo exclusivo de la portada vieja.

---

## 2. Textos de planes contra el código

Fuente: `PlanRestaurante::incluye()`, la única tabla de plan a capacidades.

| Lo que pediste decir | Código | Veredicto |
|---|---|---|
| Lite: menú editable | `Capacidad::EditarMenu` incluida; `ProductoPolicy` | Correcto |
| Lite: pedidos por WhatsApp (Domicilio / Para recoger) | No depende del plan: `pedidos_activos`, `pedidos_domicilio`, `pedidos_recoger` | Correcto, con matiz: `pedidos_activos` empieza apagado y cada restaurante lo activa. El dueño puede apagar una de las dos modalidades. |
| Aura incluye todo lo de Lite | `SitioMenu` devuelve `true` para todo | Correcto |
| Aura: sitio web "propio" | `Capacidad::Sitio`; en Lite `/{slug}` redirige 302 al menú | Correcto el sitio. **"Propio" puede confundir** (D3). |
| Aura: reservas | `Capacidad::Reservas`; `reservasDisponibles()` también pide `reservas_activas` | Correcto. El restaurante debe activarlas. |
| Aura: fotos de cada platillo | `ProductoForm` usa `ImagenRestauranteUpload` sin revisar el plan; `ProductoPolicy` lo deja a Lite; ni `MenuPublicoController` ni las vistas del menú consultan el plan | **Incorrecto: Lite también tiene fotos de platillo.** |

**Lo que sí es exclusivo de Aura en fotos:** la galería "Fotos del restaurante" (`GaleriaImagenPolicy` con `Capacidad::Sitio`). Solo se ve en las plantillas de `PLANTILLAS_CON_GALERIA`, no en todas.

**Lite tiene más de lo que dice el texto pedido:** apariencia del menú, datos, contactos, horario, QR, equipo y métricas. No es un error, solo una omisión. Con eso, el mensaje "Lite: fotos no" sería falso y "Aura: todo" se sostiene.

**Propuesta de texto (según D1, D2 y D3):**

- **Lite:** "Menú digital con QR que tú editas", "Foto de cada platillo", "Pedidos por WhatsApp: Domicilio o Para recoger", "No incluye sitio web: tu dirección abre directo el menú".
- **Aura:** "Todo lo de Lite", "Sitio web con plantilla", "Reservas en línea desde el sitio web del restaurante", "Galería de fotos del restaurante".

---

## 3. Recursos externos, CSP y peso

### Fuentes

Autoalojadas en `public/fonts/landing/`, siguiendo la forma de `public/fonts/sitio/`:

- `plus-jakarta-sans-latin-var.woff2`
- `space-grotesk-latin-var.woff2`
- `OFL.txt`, con el texto de la licencia y los dos avisos de copyright. Space Grotesk no declara nombre reservado, y la primera línea de la licencia de Plus Jakarta Sans tampoco, pero se confirma antes de publicar el subconjunto.

Los subconjuntos se generan una sola vez con `pyftsubset` y se documenta el comando. **No se añade dependencia.** Unicode: `U+0020-007E`, `áéíóúüñ` y sus mayúsculas, `¡¿«»·©€`, NBSP, `– — ‘ ’ “ ” • …`. Comprobado: no falta ningún carácter del español.

- `@font-face` en `landing.css` con `url("/fonts/landing/…")` y `font-display: swap`, igual que las plantillas.
- `preload` solo de Space Grotesk, que es la del título.
- Figtree y Space Grotesk salen de la portada, pero sus paquetes npm siguen: el panel Filament los usa.
- Space Grotesk llega a peso **700**. El prototipo le pide 800 y el navegador lo simula; se usa 700.
- El canvas debe esperar con `document.fonts.load(…)` explícito, porque `document.fonts.ready` no garantiza las fuentes que solo usa el canvas.

### three.js

- `npm install three --save-exact`, en `dependencies`. Medí con **0.187.0**; el prototipo usa 0.160.
- Nada de `importmap` ni unpkg.
- `resources/js/landing.js` es una entrada pequeña de Vite. Después del primer pintado, y solo si el criterio de la sección 4 lo permite, hace `import('./landing/escena.js')`.
- Vite genera un archivo aparte que no se descarga en la versión estática.
- Al subir de 0.160 a 0.187 hay que revisar la escena a ojo. Ya salió un aviso: `PCFSoftShadowMap has been removed`.

### CSP: **ningún cambio** en `PoliticaDeContenido::LANDING`

Probé el código real del prototipo empaquetado con Vite y servido con esa cabecera literal, en Edge headless:

| Comprobación | Resultado |
|---|---|
| Escena con three r187 | Corre: 0 violaciones de CSP y sin errores en consola. |
| Control `eval` / `new Function` | Bloqueados, así que la CSP sí estaba activa. |
| Control imagen `data:` | Bloqueada: **por eso los base64 del prototipo no pueden quedarse**. |
| `elemento.style.x = …` (lo que usan la escena y three) | Permitido. CSP solo bloquea `style=""` en el HTML y `<style>`. |
| Textura WebGL con imagen del propio dominio | Permitida. |
| Búsqueda en el bundle de la escena | 0 `eval`, 0 `new Function`, 0 `fetch`, 0 `XMLHttpRequest`, 0 `Worker`, 0 blobs. |

**`'unsafe-eval'` no hace falta.** Tampoco se relaja `connect-src 'none'`.

Lo que **no** cubre esta prueba: solo Chromium con WebGL por software, sin scroll, sin revisar el resultado visual, sin Firefox ni Safari. Se repite en la Fase 2 sobre la ruta real.

Reglas para mantenerla sin cambios:

- Todo el CSS en archivos; nunca importar CSS desde JS.
- Imágenes con `new Image()` y ruta propia; no usar `ImageBitmapLoader`, `GLTFLoader` ni `FileLoader` (usan `fetch` y pedirían abrir `connect-src`).
- La entrada de Vite trae un `fetch` del polyfill de `modulepreload`. Solo corre en navegadores sin ese soporte; ahí la CSP lo bloquea y el `import()` sigue funcionando.

### Peso medido

| Recurso | Sin comprimir | gzip | brotli | ¿Cuándo se descarga? |
|---|---|---|---|---|
| Escena + three | 543.5 KB | **134.8 KB** | **110.8 KB** | Solo en modo 3D, después del primer pintado |
| Entrada `landing.js` (aprox.) | 2.2 KB | 1.1 KB | 0.9 KB | Siempre, como módulo |
| Plus Jakarta Sans (variable 200–800) | 17.0 KB | ya comprimida | | Siempre |
| Space Grotesk (variable 300–700) | 13.5 KB | ya comprimida | | Siempre |

- Fuentes: **30.5 KB en total**, contra 49.6 KB de los archivos latinos completos.
- La escena se reducirá algo al quitar atajos y `BroadcastChannel`; three es casi todo el peso.
- Vite avisará que el archivo pasa de 500 KB. Es un aviso, no un error; no propongo cambiar la configuración global.

---

## 4. Resiliencia: la versión estática es la base

**Principio:** el HTML y el CSS ya son la página completa y legible. El 3D se activa por encima, solo si todo se cumple. Si el JS falla o nunca corre, no hay nada que recuperar: por eso sin JavaScript se ve todo, sin `<noscript>`.

### Antes del primer pintado: `public/js/landing-criterio.js`

Script clásico en `<head>`, solo en la portada. El ancho de pantalla no interviene. Agrega `landing-3d` a `<html>` salvo que ocurra alguno de estos casos:

| Se queda en estática si… | Nota |
|---|---|
| `prefers-reduced-motion: reduce` | Ni siquiera prueba WebGL |
| `sessionStorage['landing-estatica'] === '1'` | Ya se midió lento en esta sesión |
| `connection.saveData`, o `effectiveType` `slow-2g` o `2g` | 3G sí intenta el 3D |
| `deviceMemory` informado **y** (`deviceMemory` < 2 **o** `hardwareConcurrency` < 4) | iPhone y Firefox no informan memoria: ahí decide la medición de cuadros. Safari limita los núcleos que informa, por eso no se juzgan solos |
| Sin contexto `webgl2` con `failIfMajorPerformanceCaveat: true` | Rechaza el dibujado por software |

Calidad inicial: `simple` si `pointer: coarse`, o si la memoria informada es menor a 4 GB, o si hay menos de 4 núcleos con memoria informada. Si no, `alta`.

Si `landing.js` no llega en 4 s, el mismo script quita `landing-3d`.

### Durante el uso: `resources/js/landing/rendimiento.js`

| Nivel | Densidad de píxeles | Efectos | Pantalla del celular |
|---|---|---|---|
| `alta` | hasta 2 | Sombras y chispas | Cada 33 ms |
| `simple` | hasta 1.5 | Sin sombras | Cada 50 ms |
| `minima` | 1 | Sin sombras ni chispas | Cada 80 ms |

Medición: p75 de la duración de cuadros, sobre 45 cuadros al arrancar (descartando 5) y sobre 120 en uso. Los cuadros de 250 ms o más se cuentan como pausa y no entran.

| p75 | Decisión |
|---|---|
| ≤ 28 ms | Sigue igual |
| Entre 28 y 45 ms | Baja un nivel. En `minima` sigue: 30 fps (bajo consumo del iPhone) nunca apaga el 3D |
| > 45 ms | Salta a `minima`. Si ya estaba ahí: versión estática y marca en `sessionStorage` |
| Más de 8 s de arranque sin decidir, error, contexto perdido o fallo del `import()` | Versión estática |

El lienzo se muestra hasta que la medición de arranque termina bien. Si el visitante cambia de pestaña o sale de la escena, la medición vuelve a empezar: eso no dice nada del dispositivo.

### Encuadre en móvil

Composición móvil: `COMPOSICION_MOVIL` en `recorrido.js`, con la misma consulta en `landing.css`. Es una pantalla de hasta 760 px de ancho, vertical, o horizontal de hasta 500 px de alto.

- **Panel:** va arriba (a la izquierda en horizontal), opaco y compacto. "Ver un menú de ejemplo" se oculta en el hero móvil y sigue en el cierre.
- **Escena:** cada cuadro proyecta la caja de lo visible y la centra en el espacio libre que deja el panel, con `camera.setViewOffset`.
- **Entre tramos:** la escena se reduce al área común de los dos paneles antes de que el primero se funda, y crece cuando el segundo ya se ve.
- **Altura:** `--vista` usa `100svh`, no `dvh`: así la barra de Safari no tapa contenido, y el alto no cambia mientras se hace scroll, lo que evitaría saltos.

Tramos que no caben: desde el primero cuyo panel no deja 130 px libres para la escena (o no cabe en la pantalla, en horizontal), pasan al flujo normal, debajo de la escena. Con menos de 2 tramos en la escena, versión estática. Medido: 390×844 deja los cinco en la escena; 360×640 y 844×390 pasan los planes al flujo normal; 320×568, beneficios y planes.

### Costos del prototipo que se corrigen de entrada

- `drawScreen` vuelve a subir una textura de 540 × 1120 **en cada cuadro**. Se limita a cuando cambie, máximo 30 fps.
- Sombra de 2048 px y material con `transmission` (obliga a renderizar de más). Se reducen.
- El bucle se pausa fuera de la escena, con pestaña oculta y según D4.

### Cómo se midió

- Pruebas de la decisión: `tests/Js/landing-criterio.test.mjs` y `landing-recorrido.test.mjs`.
- Navegador: Edge con `playwright-core` fuera del repositorio, sobre la ruta real compilada, con GPU real (RX 580 a 178 Hz) y emulación de celular. Lentitud simulada frenando `requestAnimationFrame` y con CPU limitada por CDP.
- **Límites:** ningún teléfono real, ni Safari, ni Firefox. Limitar la CPU no frena la GPU, así que no equivale a un teléfono lento. Los umbrales no están calibrados con dispositivos reales, y con `connect-src 'none'` no hay telemetría. Falta probarlos en al menos un Android de gama baja y un iPhone.

La versión estática reutiliza lo que ya existe: `captura-menu-*.webp` y Slashi.

---

## 5. Interacción y accesibilidad

### Cambio de estructura

En el prototipo todo es `position: fixed` con `opacity: 0` y `pointer-events: none`, así que un ancla no tiene adónde ir, los enlaces no se pueden tocar y el contenido alto queda cortado. Propuesta:

- Las secciones van en el flujo normal, con `position: sticky` y alturas por tramo. **Las anclas funcionan solo con HTML y CSS**, también con la escena apagada.
- El progreso se calcula sobre la altura de la **escena**, no la del documento. Así el pie y las preguntas no alteran la animación.

| Tramo | Ancla | Progreso de su ancla | Scroll aproximado |
|---|---|---|---|
| Hero | `#contenido` | 0 | 0 |
| Escanea | `#como-funciona` | 0.24 | 48vh |
| Antójate | `#antojate` | 0.48 | 96vh |
| Beneficios | `#incluye` | 0.68 | 136vh |
| Planes | `#planes` | 0.89 | 178vh |
| Preguntas, cierre y pie | `#preguntas`, `#contacto` | Flujo normal, fuera de la escena | |

Cada tramo intermedio se ve completo durante 28 a 32vh de scroll.

### Enlaces reales

| Prototipo (`<div>` o `<span>`) | Pasa a |
|---|---|
| Cómo funciona / Beneficios / Plan / Contacto | `<a>` a `#como-funciona`, `#incluye`, `#planes`, `#contacto` |
| "Pedir demo", "Quiero Menubly" | `<a>` a WhatsApp con `enlaceWhatsapp()`. Sin número: "Quiero Menubly" lleva a `#planes` y "Pedir demo" no se pinta. |
| "Ver cómo funciona" | `<a href="#como-funciona">` |
| Tarjetas de plan | Se añade "Pregunta por este plan", como hoy, y `<h3>` para cada plan |

### Se quita

Atajos A, R, H y F, auto-scroll, `#help`, `body.clean`, `BroadcastChannel('2a2g-video')` y la barra de scroll oculta (ocultarla perjudica a quien la necesita).

### Foco y tabulación

- **Orden:** saltar al contenido, logo, menú, WhatsApp, acciones del hero, botones de planes, preguntas, cierre, pie. Es el orden del HTML; sin `tabindex` positivo.
- Foco visible con el token `--focus` que ya existe.
- Un enlace en un tramo con `opacity: 0` no puede quedar enfocado e invisible. Un manejador de `focusin` lleva el scroll al inicio de su tramo, solo en modo 3D.
- Canvas, barra de progreso y "DESLIZA" con `aria-hidden`. El QR dibujado **no se puede escanear** (es aleatorio) y es decorativo.

### Contraste 4.5:1

Medido. Los tokens de `landing.css` ya advierten que `--blue` es "solo gráficos" y `--sky` "nunca texto"; el prototipo incumple eso.

| Uso | Prototipo | Propuesta (token que ya existe) |
|---|---|---|
| `.pill` | `#2B7FD6` / blanco: **4.11, falla** | `--link`: 6.69 |
| `.tag` | `#2B7FD6` / hielo: **3.74, falla** | `--link`: 6.09 |
| `.cta1` | Blanco / `#2B7FD6`: **4.11, falla** | `.btn--primary` (blanco / navy): 12.46 |
| `.hl` (degradado) | **3.74 a 1.72, falla** | Color sólido `--link`: 6.09 sobre hielo, 5.73 sobre `#DDF0FF` |
| `.sub` | `#5B6B82` / hielo: 4.94; pasa solo sin 3D detrás | `--ink-soft`: 6.86 |
| Títulos | Navy / hielo: 11.34 | Igual |

**Texto sobre la escena:** el velo del prototipo es un degradado que baja a 0.75 y luego a 0. Con el celular detrás da 4.04 en `--ink-soft` y 3.59 en `--link`: falla, y más allá del degradado queda sin protección.

Se cambia por un **panel detrás del texto**, con hielo al 92 %. Incluso sobre negro, el peor píxel posible, da aproximadamente: navy 9.5, `--ink-soft` 5.7, `--link` 5.1. Las tarjetas de beneficios y planes pasan a blanco sólido (hoy `.82` con desenfoque). Así el contraste no depende de lo que dibuje el 3D en cada momento.

---

## 6. Imágenes

Tres de las cinco **ya existen en el repositorio**; no hay que crear esos archivos.

| Imagen (línea) | Hoy | Comprobación | Destino | Peso final |
|---|---|---|---|---|
| Favicon (8) | SVG 1,638 B | **Idéntica** a `public/favicon.svg` | Ya sale de `<x-mb.favicons />` | 1,638 B |
| Logo (101) | SVG 4,701 B | **Idéntica** a `brand/menubly-horizontal-color.svg` | Ya sale de `cabecera` | 4,701 B |
| Chef (132) | PNG 1024×523, 32,002 B | Misma imagen a la vista que Slashi (no es idéntica byte a byte) | `landing/img/slashi-chef-color-{240,480}.webp` | 3,822 / 7,426 B |
| Logo blanco (163) | PNG 1024×215, 15,446 B | Solo se dibuja en el canvas, hasta 300 px | **Nuevo:** `landing/img/menubly-negativo-306.webp` | 3,834 B |
| Símbolo (163) | PNG 512×512, 17,759 B | **Idéntico** a `brand/icon-512.png`; en el QR se dibuja a unos 87 px | **Nuevo:** `landing/img/menubly-simbolo-128.webp` | 2,722 B |

- **Peso nuevo: 6.6 KB**, que solo se descarga en modo 3D. Se quitan unos 95 KB de base64 del HTML.
- Los logos de `<img>` siguen siendo dos (cabecera y pie): la prueba los cuenta. Los del canvas se piden desde JS.
- `/landing/*` ya está en las reglas de caché de Cloudflare.

---

## 7. SEO y pie

| Elemento | Propuesta |
|---|---|
| `title` | `{{ config('app.name') }} | Menú digital con QR para restaurantes` (con NFC encendido: "con QR y NFC") |
| `meta description` | "Tus clientes escanean un QR y se abre tu menú, sin descargar nada. Tú lo editas y recibes pedidos por WhatsApp." |
| `canonical` | `rtrim(config('app.url'), '/').'/'`, como hoy |
| Open Graph | Se conserva: `type`, `locale` `es_MX`, `site_name`, `url`, imagen `brand/og-image-1200x630.png` y sus medidas. Título: "{{ app.name }}: tu menú, a un escaneo". Descripción sin NFC (D1). |
| Twitter | `summary_large_image` |
| JSON-LD | `Organization`, igual. La CSP no lo bloquea porque es un dato. |
| Encabezados | Un `h1`. Cada tramo con `h2`. Cada plan con `h3`. |
| Otros | `lang="es-MX"`, `color-scheme` light, sin `noindex` en `/`. `theme-color` se mantiene en el layout porque lo comparten las páginas legales. |

**Pie:** `partials/pie.blade.php` sin cambios: logo negativo, Privacidad, Términos, Contacto por WhatsApp (si hay número), Entrar y ©.

**Antes de publicar:** `/privacidad` y `/terminos` siguen con `[PÁGINA POR ESCRIBIR]`. La nueva landing enlazará a esas páginas sin contenido; `menuonl:verificar-produccion` lo avisa. No es parte de este trabajo, pero conviene resolverlo.

---

## 8. Pruebas

Se corren solo las que cubren el cambio (`php artisan test --compact` con ruta), más `pint --dirty` si se toca PHP.

### Existentes

**Lo esperado: si se respeta este plan, ninguna tiene que cambiar.** Son el contrato de la página; si una falla, primero se revisa la página y no la prueba.

| Prueba | Qué obliga a conservar |
|---|---|
| `PortadaTest`: cabeceras, CSP y caché | `LANDING` sin cambios, sin cookies, caché pública |
| `PortadaTest`: sin código en línea | Sin `style=`, `<style>` ni `on*=`; sin importmap en línea; todo script con `src` o JSON-LD |
| `PortadaTest`: WhatsApp y sin número | Número de config, `rel` seguro, sin `target="_blank"`. Sin número: sin `wa.me` y sin `WhatsApp</span>` |
| `PortadaTest`: pie | `href=".../admin">Entrar</a>`, privacidad y términos |
| `PortadaTest`: menú de ejemplo | Validación y texto "Ver un menú de ejemplo" |
| `PortadaTest`: planes | `<h3 id="plan-lite">`, `$249 al mes`, frase de precios de la pregunta, sin "Completo" |
| `PortadaTest`: sin nota | Clase `plans__nota` |
| `PortadaTest`: secciones e ids | Los cuatro ids una sola vez, sin duplicados (por eso cada tramo se pinta una sola vez) |
| `PortadaTest`: logos | Exactamente 2 `<img>` de logo, sin `hidden` ni `loading=` |
| `PortadaTest`: marcadores | `MarcadoresPendientes` renderiza `landing.inicio` solo con `soporte` y `demoUrl`: **la vista no puede exigir variables nuevas** |
| `UxUiAccesibilidadTest` | Vista `landing.inicio`, `lang="es-MX"`, sin consultas en la vista |
| `LogoMenublyTest`, `CabecerasDeSeguridadTest` | Logo en `/`, scripts solo propios, CSP por grupo |

### Nuevas (`LandingNuevaTest` y `tests/Js/`)

| Grupo | Qué prueba |
|---|---|
| Planes desde config | Con precios inventados en config, salen esos; el `$499` del prototipo nunca aparece |
| Texto de planes | Lite y Aura dicen lo acordado. Con `PlanRestaurante::incluye()`: Lite sin `Sitio` ni `Reservas`, Aura con ellos, para que la portada no contradiga la tabla |
| NFC (según D1) | "NFC" no aparece en HTML ni en `escena.js` |
| Enlaces | Los CTA y el menú son `<a href>`; cada ancla interna tiene su `id`; WhatsApp en cada plan |
| Sin WhatsApp | Sin número: "Quiero Menubly" apunta a `#planes` y no queda un enlace vacío |
| Sin recursos externos | Ni HTML ni `landing.css` ni fuentes de JS contienen `unpkg`, `googleapis`, `gstatic`, `jsdelivr` ni `data:image` |
| Fuentes locales | Los `.woff2` y `OFL.txt` existen (patrón de `SitioCafeTest`); `@font-face` apunta a `/fonts/landing/`; cada archivo pesa menos de 20 KB |
| Respaldo sin JS | La respuesta del servidor ya trae títulos, planes, precios, preguntas y pie; sin `hidden` ni `inert`; sin clase `landing-3d` en el HTML servido |
| Legales intactas | `/privacidad` y `/terminos` no cargan `landing.js`, `landing-criterio.js` ni three |
| Sin atajos | Ni `#help` en el HTML ni `keydown`, `BroadcastChannel` o `requestFullscreen` en el JS |
| CSP | `LANDING` conserva `script-src 'self'`, sin `unsafe-eval` ni `unsafe-inline`; `connect-src 'none'` |
| Bundle | Con manifest (CI compila antes de probar): three aparece solo como importación dinámica de `landing.js`, y el archivo no contiene `eval(` ni `new Function` |
| Criterio (`tests/Js`) | `landing-criterio.js` con cada condición en falso no agrega la clase; todas en verdadero sí; `sessionStorage` que falla no rompe |
| Contraste (`tests/Js`) | Calcula 4.5:1 con los tokens reales de `landing.css` para cada pareja de la sección 5 |

Los `tests/Js/*.test.mjs` no necesitan configuración nueva: `tests/Unit/PedidoWhatsappJsTest.php` ya los ejecuta todos con `node --test`, también en CI.

**Queda como verificación manual**, porque el proyecto no tiene Playwright como dependencia y no se añade: render 3D, foco visible, y la escena bajo CSP en navegador. Esta máquina no tiene Chrome donde lo busca el plugin de Playwright; Edge headless sí sirvió.

---

## 9. Riesgos y archivos

### Migraciones y datos

**Ninguna.** Sin cambios en base de datos, rutas, controlador, `PoliticaDeContenido`, `CabecerasDeSeguridad` ni `ContactoSoporte`. `config/menuonl.php` solo suma `nfc_disponible`.

### Archivos

| Acción | Archivo | Cambio |
|---|---|---|
| Reescribe | `resources/views/landing/inicio.blade.php` | Estructura por tramos y contenido |
| Edita | `resources/css/landing.css` | Fuentes y estilos de tramos; se quita lo exclusivo de la portada vieja; se conservan base, cabecera, pie y legales |
| Edita | `resources/views/landing/partials/cabecera.blade.php` | Menú con Beneficios y Contacto |
| Edita | `vite.config.js` | Agrega `resources/js/landing.js` a `input` |
| Edita | `package.json`, `package-lock.json` | `three` (D5) |
| Edita | `docs/despliegue.md` (5.1 y 6.1) | JS de la portada; agregar `/fonts/*` a la regla de caché, que hoy no está |
| Crea | `resources/js/landing.js`, `resources/js/landing/escena.js` | Activación y escena |
| Crea | `public/js/landing-criterio.js` | Decisión antes del pintado |
| Crea | `public/fonts/landing/` (2 `.woff2` + `OFL.txt`) | Fuentes |
| Crea | `public/landing/img/menubly-negativo-306.webp`, `menubly-simbolo-128.webp` | Texturas del canvas |
| Crea | `tests/Feature/LandingNuevaTest.php`, `tests/Js/landing-*.test.mjs` | Pruebas |
| Borra, si ya nadie los usa | `partials/red-hero.blade.php`, `partials/red-cierre.blade.php` | Redes de puntos de la portada vieja |
| **No se toca** | `layout.blade.php`, `pie.blade.php`, `privacidad`, `terminos`, `landing-reveal.js`, `PortadaController`, `.env`, `public/hot`, respaldos | |

`public/build` se regenera al compilar, según D5.

### Riesgos

| Riesgo | Control |
|---|---|
| **Entrada nueva de Vite:** con un `public/build` viejo, `@vite('resources/js/landing.js')` da "Unable to locate file" y **`/` responde 500**, también en pruebas locales. La vista conserva el guardado `$hayBuild`, pero solo cubre que no exista manifest. | D5: compilar antes de probar. CI y despliegue ya compilan. |
| Las páginas legales dependen del mismo CSS | Prueba específica y revisión visual de las dos |
| Un teléfono capaz cae a estática, o uno lento se queda en 3D | Umbrales sin calibrar en dispositivos reales (sección 4). Probar antes de publicar |
| Tramos con contenido alto en pantallas justas | Los que no caben pasan al flujo normal, medido en cada carga y al cambiar de tamaño |
| Salto al pasar de 3D a estática durante el uso | Solo ocurre por fallo o lentitud; se conserva la sección actual al cambiar |
| Scroll de la escena (300vh) antes de ver precios | Anclas y CTA directos a `#planes` |
| Alguien intenta escanear el QR dibujado y no abre nada | Es decorativo. Si te preocupa, se puede sustituir por un QR real a la demo en una fase posterior |
| Tras publicar, Cloudflare sirve la portada anterior | Purgar `/`, `/privacidad`, `/terminos` (`docs/despliegue.md` 5.1) |
| Three.js cambia entre versiones | Versión exacta; revisar a mano al subir |
| Licencias | three: MIT. Fuentes: OFL 1.1, con `OFL.txt` publicado |

### Orden propuesto para la Fase 2 (tras tus respuestas)

1. Contenido y versión estática completa (`inicio`, CSS, fuentes, pruebas). Por sí sola ya sería publicable.
2. `landing-criterio.js` y sus pruebas.
3. `three` y escena bajo CSP, sin atajos, con la calidad ajustada.
4. Tramos, anclas, foco y contraste.
5. Matriz manual, Lighthouse y `docs/despliegue.md`.
