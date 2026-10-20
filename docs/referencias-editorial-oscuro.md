# Referencias para el tema "Editorial oscuro"

Investigación hecha el 2026-10-19. No se modificó código. No se descargó ni copió ninguna imagen, texto o código de los sitios revisados.

**Límite importante de esta investigación.** La herramienta de lectura web devuelve texto y estructura, no la página renderizada. El navegador automatizado no estaba instalado en esta máquina y no se instaló. Por eso, de los sitios de referencia pude verificar **estructura, contenidos y cómo resuelven reserva, horarios y contacto**. **No pude verificar** colores, tipografías, fondo oscuro ni uso del dorado en ninguno. Lo visual de la sección 3 sale de nuestro propio código y de cálculos de contraste, y se marca como propuesta cuando no es una observación. La imagen de referencia que se menciona en el encargo no llegó adjunta al mensaje; no la usé.

---

## 1. Lo que el tema editorial tiene hoy

Hay dos cosas distintas con nombre parecido, y conviene no confundirlas:

### 1.1 El tema "Editorial oscuro" (`Catalogos::temas()`), que solo afecta a `/m`

- Definición: `app/Services/Catalogos.php:105`. Nombre "Editorial oscuro", acento por defecto `#c9a24a`.
- Se aplica con `data-tema="editorial"` en `resources/views/components/layout/publico.blade.php:69`, y lo usa solo `resources/views/menu/publico.blade.php`. Los colores viven en `public/css/menuonl.css`.
- **No es oscuro por defecto.** En modo claro (`menuonl.css:144`) el fondo es crema `#f6f3ec`, la superficie `#fffdf8` y el acento `#7d5f17`. Solo se vuelve oscuro si el teléfono está en modo oscuro (`menuonl.css:267`) o si el usuario fuerza la apariencia: fondo `#0f0f14`, texto `#e9e4da`, acento `#c9a24a`. La descripción del catálogo ("Fondo oscuro, detalles dorados…") no coincide con lo que ve un cliente con el teléfono en modo claro.
- Tipografía de títulos: pila `'Didot', 'Bodoni MT', 'Playfair Display', 'Palatino Linotype', Georgia, serif` (`menuonl.css:71`). **Ninguna de esas fuentes está alojada**: se usa lo que tenga instalado el dispositivo, así que el aspecto varía entre iPhone, Android y Windows.
- Extras del tema: radios casi rectos (0.125rem), `--mo-mayusculas: uppercase`, `--mo-espaciado-titulo: 0.08em`.
- El dueño puede cambiar el acento con `color_acento` (panel, `EditRestauranteProfile.php:63`). Ese valor reemplaza al acento del tema tanto en modo claro como oscuro.

### 1.2 Las plantillas del sitio `/s/{slug}` (a, b, c)

- Catálogo: `Catalogos::plantillasSitio()`. La **b, "Oscuro elegante"**, es la única realmente oscura: fondo `#0B0B0A`, panel `#141311`, texto `#F4EFE6`, acento "arena" `#C9B08A` (`resources/css/sitio/b.css`). El acento no es el dorado `#c9a24a`.
- Estructura de `b.blade.php`: cabecera fija con botón "Reservar", portada a pantalla completa con velo degradado, "Nosotros" (cita con la descripción), "Menú destacado" (hasta 6 platos, filas con foto 4:5), galería en mosaico, reserva (formulario propio), ubicación con horarios, pie.
- Tipografía ya alojada en `public/fonts/sitio/`: Cormorant Garamond 500 normal (23.3 KB), 500 cursiva (23.9 KB), 600 normal (23.4 KB) e Inter variable (68.3 KB). Carga con `font-display: swap` y precarga de dos archivos. La cursiva de Cormorant ya existe, así que "una palabra en cursiva dorada" no exige fuente nueva.
- Imágenes: componente `x-sitio.imagen` con `srcset` de 480, 960 y 1600 px, WebP a calidad 82, portada con `fetchpriority="high"` y sin `loading=lazy`. Sin foto, muestra un bloque neutro `foto-vacia` con la misma proporción.
- Datos disponibles (`SitioPublicoDatos`): nombre, eslogan, descripción, portada, logo, hasta 6 destacados (con foto, precio, categoría), galería, horarios por día, dirección, teléfono, WhatsApp, Instagram, Facebook, enlace a mapa, reservas activas.

### 1.3 Hallazgos que importan para el rediseño

1. **El sitio `/s` ignora `tema` y `color_acento`.** Cada plantilla trae sus colores fijos. Elegir "Editorial oscuro" en el panel solo cambia `/m`. Hay que decidir si el tema editorial es una plantilla de `/s`, un tema de `/m`, o ambos.
2. **Hay textos del diseño fijos**, lo que choca con la regla de que ningún texto sea fijo. En `b.blade.php`: "Una mesa, una historia.", "Lo que pedirás dos veces.", "Luz, vajilla, fuego.", "Reserva tu mesa.", "Te esperamos.", "Nosotros", "Ver menú completo". En a y c pasa igual ("De temporada.", "La mesa.", "La casa", "Instantáneas"…).
3. **No hay campo de historia propio.** La sección "Nosotros" usa `descripcion`, que es una sola cadena. Tampoco hay textos editables de cada sección.
4. El `/m` editorial no tiene portada con título, historia ni banda de reserva; es una carta, no un sitio.
5. En `public/fonts/sitio/` solo hay licencia de Cormorant (`OFL-cormorant.txt`). Inter también es OFL (lo dice `fuentes.css`) y su texto de licencia no está en esa carpeta.

---

## 2. Sitios de referencia

Los 14 se abrieron con la herramienta de lectura web el 2026-10-19. "Estética" se deja como **no verificada** en todos porque solo obtuve texto. La columna "Rescatar" es lo observado en el contenido.

| # | Sitio | URL | Rescatar (observado) | Evitar |
|---|---|---|---|---|
| 1 | Quintonil (CDMX) | https://www.quintonil.com | Menú de navegación corto con botón RESERVACIONES; texto de presentación con fundadores; galería de 4 fotos; carta de degustación como lista de texto con separadores y notas al pie (adaptaciones, alérgenos); bloque de ubicación con dirección, teléfonos, mapa y estacionamientos cercanos; horario en una línea (lunes a sábado 13:00 a 24:00, domingo cerrado). | Logos de premios y menciones (no podemos afirmar nada que no venga del restaurante); portada tipo carrusel de cuatro banners (peso). |
| 2 | Rosetta (CDMX) | https://www.rosetta.com.mx | Navegación clara con Menú, Reservaciones y Contacto; dirección con enlace a mapa. | La página principal casi no muestra contenido: horarios y contacto quedan en subpáginas; imágenes sin texto alternativo en el HTML leído. |
| 3 | Contramar (CDMX) | https://www.contramar.com.mx | Carta en secciones con precios (Barra Fría, Tostadas, Platos Fuertes…); horarios por día; teléfono, WhatsApp y correo en un bloque; nota de precios con IVA y de disponibilidad por temporada. | Tres imágenes sin texto alternativo; sin titular de portada. |
| 4 | Máximo Bistrot (CDMX) | https://www.maximobistrot.com.mx | Titular de portada que es una frase de intención (no solo el nombre); texto breve de filosofía; horario, dirección y contacto en pocas líneas. | Menú y reserva en páginas aparte sin vista previa en portada. |
| 5 | Central (Lima) | https://centralrestaurante.com.pe | Titular de varias líneas en mayúsculas; horario separado por servicio (almuerzo y cena); horarios y contacto en el pie. | Reserva y horarios solo como texto plano en el pie; enlace de correo con el sufijo duplicado (error visible en el texto leído). |
| 6 | Maido (Lima) | https://www.maido.pe | Titular de bienvenida con botón RESERVA AQUÍ en la portada; texto breve tipo declaración de marca; experiencias con precio por persona; reglas de reserva explícitas (con cuántos meses de anticipación, tamaño de grupo, a quién escribir). | Horario incoherente dentro de la misma página (cena hasta 21:00 en un bloque, 22:00 en otro): un solo dato de horario por restaurante evita esto. |
| 7 | Astrid y Gastón (Lima) | https://www.astridygaston.com | Sección "Nuestra historia" con relato corto; horarios por día con excepciones (lunes cerrado, domingo solo comida); carta como PDFs enlazados. | Carta solo en PDF (mala en móvil, no indexable); sin teléfono ni dirección completa en el texto leído. |
| 8 | Atomix (Nueva York) | https://www.atomixnyc.com | Solo se leyó la cabecera: logotipo y cuatro enlaces (Collabs, Chef's Counter, Bar Tasting, About Us). Navegación mínima. | Contenido insuficiente para evaluar más. |
| 9 | Alinea (Chicago) | https://www.alinearestaurant.com | Foto grande, título corto, enlaces de reserva inmediatamente debajo; tres experiencias con foto; rejilla de 6 imágenes; dirección y contactos en el pie. | Mensaje de reserva dividido por fechas en dos plataformas (confuso); párrafo de premios. |
| 10 | Noma (Copenhague) | https://noma.dk | Botón "Book a table" en la portada; horario y teléfono con días y franjas de atención; dirección con mapa. | Página convertida en tablón de anuncios (tienda, libro, café, otros negocios); banner de cookies que tapa la portada. |
| 11 | Saison (San Francisco) | https://saisonsf.com | Titulares de portada cortos y declarativos; "Reservations" como primer enlace del menú. | No muestra horario ni dirección en la página leída; promoción de eventos en el primer pliegue. |
| 12 | Lazy Bear (San Francisco) | https://www.lazybearsf.com | Titular con personalidad ("A Modern American Dinner Party") y párrafo breve; índice de secciones repetido. | Sin horario, dirección ni forma de reserva visibles en el texto leído. |
| 13 | Mugaritz (País Vasco) | https://www.mugaritz.com/en/ | Narrativa por secciones con un llamado final a reservar; fotos con pie descriptivo; temporada con fechas. | Lenguaje muy conceptual; sin horario, dirección ni teléfono en el texto leído. |
| 14 | Dishoom (Reino Unido) | https://www.dishoom.com | Dos botones juntos en la portada (ver menús y reservar); resumen de cada local; texto cálido y específico. | Cadena con mucho contenido secundario (tienda, recetas, boletín); no hay horarios en la portada. |

No pude abrir: **Pujol** (https://www.pujol.com.mx, respondió 429), **Don Julio** (www.donjulio.com.ar no resolvió) ni **The Clove Club** (www.theclove.club no resolvió; la dirección puede ser otra). No los describo.

Cobertura de lo pedido:
- Independientes y no cadenas: todos menos Dishoom.
- México o Latinoamérica: Quintonil, Rosetta, Contramar, Máximo, Central, Maido y Astrid y Gastón (7).
- **Sitios oscuros con serifa y acento cálido: no pude confirmar ninguno.** Quedan sin verificar tanto el fondo oscuro como la serifa. Si quieres referencias visuales confiables, hace falta que alguien con navegador las abra y capture los datos (ver sección 7).

---

## 3. Patrones recomendados

Costo: **poco** = cambia solo Blade y CSS de una plantilla; **medio** = además agrega campos o migración y un formulario en el panel; **mucho** = estructura nueva. Todos caben en Blade sin framework nuevo.

| # | Patrón | Evidencia | Cabe hoy | Costo |
|---|---|---|---|---|
| P1 | **Portada con un titular y una sola acción principal** (reservar), con una segunda acción secundaria (ver menú). | Maido y Noma (botón en portada), Dishoom (dos botones), https://www.maido.pe https://noma.dk https://www.dishoom.com | Ya existe en `b.blade.php` (Reservar mesa y Ver menú). | Poco |
| P2 | **Título en serifa con una palabra en cursiva dorada.** La palabra se elige del texto del restaurante, por ejemplo la última palabra del eslogan, o un campo opcional "palabra destacada". | Propuesta propia (no se pudo verificar en los sitios). Cormorant cursiva 500 ya está alojada. | Sí. Hay que partir el eslogan en Blade y envolver una palabra en `<em>`, con escape. | Poco si se usa la última palabra; medio si es un campo editable |
| P3 | **Botón de reservar siempre a mano**: en la cabecera y en una barra fija en móvil. | Quintonil (botón en la navegación), Saison (primer enlace), https://www.quintonil.com https://saisonsf.com | Cabecera ya la tiene. Falta barra fija móvil (la tuvo el prototipo anterior). | Poco |
| P4 | **Reglas de reserva explícitas** (anticipación, tamaño de grupo, a quién escribir) junto al formulario. | Maido, https://www.maido.pe | Parcial: `config('menuonl.reservas.*')` ya limita días y personas. Mostrar ese límite como texto es fácil; que el dueño edite su propio texto no existe. | Poco para mostrar límites; medio para texto editable |
| P5 | **Horario en forma compacta** ("Lunes a sábado 13:00 a 24:00, domingo cerrado") en lugar de siete filas, y por servicio cuando aplica. | Quintonil, Central, https://www.quintonil.com https://centralrestaurante.com.pe | Hoy es una tabla de 7 filas (`partes/horarios.blade.php`). Agrupar días consecutivos iguales se hace en `SitioPublicoDatos::horarios()`. | Poco a medio |
| P6 | **Estado "abierto ahora" y turnos de hoy** como dato rápido junto al título. | Propuesta; no observado en los sitios. Ya existe `estaAbiertoAhora()` en el modelo. | Sí, pero `/s` no lo calcula. | Poco |
| P7 | **Bloque de contacto con dirección, teléfono, WhatsApp, mapa y estacionamiento/indicaciones.** | Quintonil, Contramar, https://www.quintonil.com https://www.contramar.com.mx | Ya existe salvo indicaciones adicionales. | Poco |
| P8 | **Carta corta en texto** (nombre, descripción, precio, separadores finos) en vez de tarjetas con foto cuando faltan fotos. | Quintonil y Contramar (listas de texto con precios), https://www.quintonil.com https://www.contramar.com.mx | Sí: los destacados ya traen nombre, descripción y precio. Sin foto, la tarjeta cambia a modo texto. | Poco |
| P9 | **Platillos destacados en tarjetas** con foto vertical, nombre y precio (la intención de la imagen de referencia). | Propuesta; en los sitios leídos no pude ver tarjetas. | Sí, con `x-sitio.imagen`. | Poco |
| P10 | **Historia breve** como bloque propio, con título y uno o dos párrafos. | Maido (declaración de marca), Máximo (filosofía), Astrid y Gastón (historia), https://www.maido.pe https://www.maximobistrot.com.mx https://www.astridygaston.com | Hoy solo `descripcion`. Para título y dos párrafos hacen falta campos nuevos. | Medio |
| P11 | **Galería de 4 a 6 fotos** como rejilla sobria. | Quintonil (4), Alinea (6), https://www.quintonil.com https://www.alinearestaurant.com | Ya existe. | Poco |
| P12 | **Banda de reservación** a todo el ancho entre secciones, con el botón y los medios alternos (WhatsApp y teléfono). | Maido (reglas y contacto), Mugaritz (llamado final a reservar), https://www.maido.pe https://www.mugaritz.com/en/ | Ya existe como caja de reserva; falta la banda corta antes del pie. | Poco |
| P13 | **Acento dorado en poco espacio**: solo texto corto, líneas finas, botón principal, precios y cursiva del título. Nunca sobre foto y nunca en párrafos largos. | Propuesta propia; justificada con los contrastes de la sección 5. | Sí; es una regla de CSS. | Poco |
| P14 | **Portada sin foto resuelta con tipografía**: fondo de color con degradado sutil y el título grande, sin bloque gris vacío. | Propuesta propia; hoy `foto-vacia` es un bloque gris. | Sí: `b.css` ya tiene variante sin foto en `.hero`; falta tratarla como diseño y no como hueco. | Poco |
| P15 | **Textos de cada sección editables** (título y etiqueta de cada bloque) con valores por defecto neutros. | Requisito de diseño ya decidido; hoy incumplido (1.3, punto 2). | No: requiere campos y panel. | Medio a mucho |

**Qué hacen los sitios cuando no hay fotos buenas:** no pude observarlo. Los sitios leídos siempre tenían imágenes. Mi propuesta es P14 y P8.

---

## 4. Tipografías propuestas (alojadas en nuestro servidor)

Todas son OFL-1.1 según las páginas de Fontsource consultadas. Son licencias que permiten alojarlas y servirlas desde nuestro dominio. Los pesos de archivo se midieron solo para las que ya están en el repositorio; para las demás **no pude verificar el tamaño**, y no pongo cifras inventadas: hay que medirlas al descargar el subconjunto latino en woff2.

| Fuente | Rol | Licencia | Estilos y pesos (según Fontsource) | Peso aproximado |
|---|---|---|---|---|
| **Cormorant Garamond** (ya en el repositorio) | Títulos y cursiva dorada | OFL-1.1, texto en `public/fonts/sitio/OFL-cormorant.txt` | En uso: 500 normal, 500 cursiva, 600 normal | Medido: 23.3 KB, 23.9 KB y 23.4 KB (latín) |
| **Inter** (ya en el repositorio) | Texto de lectura | OFL-1.1; falta incluir su texto de licencia junto a los archivos | Variable | Medido: 68.3 KB (latín, variable) |
| **Fraunces** | Alternativa de títulos, más "de autor" | OFL-1.1 | Variable, eje de peso 100 a 900; normal y cursiva | No verificado |
| **Playfair Display** | Alternativa de títulos de alto contraste (estilo Didot) | OFL-1.1 | Variable, 400 a 900; normal y cursiva | No verificado |
| **Bodoni Moda** | Alternativa más cercana a Didot/Bodoni, que es lo que el tema editorial nombra hoy | OFL-1.1 | Variable, 400 a 900; normal y cursiva | No verificado |
| **Instrument Serif** | Titulares grandes con cursiva elegante | OFL-1.1 | Un solo peso (400). La página ofrece estilo cursiva, sin confirmar si es archivo propio | No verificado |
| **Newsreader** | Serifa de lectura para párrafos largos de historia | OFL-1.1 | Variable, 200 a 800; normal y cursiva | No verificado |
| **DM Sans** | Alternativa sans a Inter | OFL-1.1 | Variable, 100 a 1000; normal y cursiva | No verificado |

Recomendación: **no agregar fuentes todavía.** Cormorant Garamond (con su cursiva) e Inter ya cubren el patrón de la imagen de referencia, ya están alojadas y precargadas, y suman unos 139 KB. La inconsistencia que sí conviene arreglar es el tema `/m`, que nombra Didot, Bodoni y Playfair sin alojar ninguna: usar Cormorant o una de las anteriores en `menuonl.css` daría el mismo aspecto en todos los teléfonos. Si se agrega una segunda serifa, elegir una sola y subconjunto latino.

Detalles de implementación a tener en cuenta (no son afirmaciones sobre las fuentes): servir solo el subconjunto latino en woff2, `font-display: swap`, precargar como máximo las dos fuentes de la portada.

---

## 5. Accesibilidad y rendimiento

### 5.1 Contraste (WCAG 2.2 AA)

Criterio: texto normal 4.5:1; texto grande (18 pt, unos 24 px, o 14 pt en negrita, unos 18.5 px) 3:1; fuente: https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html. El mismo documento dice que un fondo de imagen que no da contraste suficiente es un fallo (F83) y que no se redondea (4.499 no cumple 4.5). Cálculos propios con la fórmula de luminancia relativa de WCAG:

| Combinación (valores del código actual) | Razón |
|---|---|
| `/m` editorial oscuro: texto `#e9e4da` sobre `#0f0f14` | 15.08 |
| Texto suave `#a39d90` sobre superficie `#17171e` | 6.61 |
| Acento `#c9a24a` sobre fondo `#0f0f14` | 7.96 |
| Acento `#c9a24a` sobre superficie `#17171e` | 7.43 |
| Texto del botón `#17171e` sobre acento `#c9a24a` | 7.43 |
| Borde de control `#7d7889` sobre `#0f0f14` (mínimo 3:1 para componentes) | 4.48 |
| `/m` editorial claro: acento `#7d5f17` sobre `#f6f3ec` | 5.38 |
| **Acento dorado `#c9a24a` sobre el fondo crema claro `#f6f3ec`** | **2.17 (no cumple)** |
| Sitio B: texto `#F4EFE6` sobre `#0B0B0A` | 17.19 |
| Suave `#B8B0A2` sobre panel `#141311` | 8.64 |
| Arena `#C9B08A` sobre `#0B0B0A` | 9.43 |
| Borde de campo `#8B8374` sobre `#0B0B0A` | 5.25 |
| Líneas decorativas `#3A3732` y `#2e2e3a` | 1.4 a 1.7 (decorativas, no exigen contraste) |

Conclusiones:
- Sobre fondo oscuro plano, el dorado **sí** cumple AA para texto, con margen. No hay que oscurecerlo.
- **Riesgo:** `color_acento` reemplaza el acento tanto en claro como en oscuro. Si un dueño deja o elige `#c9a24a` y el cliente ve el modo claro, el acento sobre crema da 2.17. Hoy el código solo calcula un texto legible **encima** del acento (`colorAcentoTexto`), no valida el acento contra el fondo. Habría que validarlo o tener un acento distinto por modo.
- En el sitio B, las líneas de 1 px son decorativas; lo que sí necesita 3:1 son los bordes de campos de formulario (cumplen, 5.25).

### 5.2 Texto sobre foto

Cálculo con un velo negro `#0B0B0A` de opacidad `a` sobre el peor caso de foto (blanco puro) y sobre un gris medio. Texto crema `#F4EFE6` y dorado `#c9a24a`:

| Opacidad del velo | Crema sobre foto blanca | Crema sobre gris medio | Dorado sobre foto blanca | Dorado sobre gris medio |
|---|---|---|---|---|
| 0.40 | 2.37 | 6.93 | 1.13 | 3.31 |
| 0.60 | 4.52 | 9.94 | 2.16 | 4.74 |
| 0.70 | 6.61 | 11.87 | 3.16 | 5.66 |
| 0.80 | 9.64 | 13.89 | 4.60 | 6.63 |
| 0.93 | 14.89 | 16.23 | 7.11 | 7.75 |

Qué implica:
- El degradado actual del sitio B empieza en `#0B0B0A66` (opacidad 0.4) arriba y llega a 0.93 abajo. Abajo, donde va el titular, cumple de sobra. **Arriba, donde van el logotipo y los enlaces, el velo es 0.4 y, con una foto muy clara, el crema cae a 2.37 (no cumple).** La cabecera tiene su propio degradado `#0B0B0Aee` a transparente, que ayuda en los primeros píxeles pero se desvanece. Es un caso a probar con fotos claras.
- **El dorado nunca debe ir sobre foto**: solo cumple con un velo cercano a 0.8 o más. El dorado va sobre fondo plano.
- Recomendaciones: (a) en móvil dejar la foto arriba y el texto debajo sobre fondo plano, como ya hace `b.css` bajo 900 px; (b) en escritorio, velo de al menos 0.7 detrás de cualquier texto, o un panel sólido detrás del titular; (c) la cabecera con fondo sólido o semitransparente fuerte al hacer scroll; (d) no usar texto en tamaño pequeño sobre foto.
- Un texto con sombra no sustituye al velo: el criterio se mide contra el fondo.

### 5.3 Peso de la portada en móvil con mala señal

Fuente: https://web.dev/articles/optimize-lcp. Meta de LCP: 2.5 s o menos en al menos el 75 % de las visitas. Reglas que cita: no cargar la imagen de portada con lazy loading, poner `fetchpriority="high"` en ella (en una o dos imágenes como máximo), que el `<img>` esté en el HTML inicial con `src` o `srcset`, y usar formatos modernos y compresión.

Estado actual: ya cumplimos la parte de marcado. `x-sitio.imagen` genera `srcset` con 480, 960 y 1600 px, en WebP calidad 82, y la portada lleva `fetchpriority="high"` sin lazy. Dos observaciones:
- La portada declara `sizes="100vw"`. En un teléfono de 390 px el navegador elegirá el archivo de 480 o 960 según la densidad de pantalla; con una pantalla de 3x pedirá el de 1600 px. Probar con una portada real y medir.
- Se aceptan imágenes de hasta 4096 x 4096 px; el servicio las reduce, pero un dueño puede subir fotos pesadas. El peso real de cada variante no lo medí.

Propuesta (no es dato verificado): fijar un presupuesto de peso para la variante móvil de la portada y revisarlo con una prueba de red lenta antes de lanzar. Si falla, bajar la calidad de la variante de 480 px o limitar el 1600 px a pantallas anchas.

Otras decisiones de bajo costo: no usar carrusel en la portada (Quintonil carga cuatro banners; no medí su costo), precargar solo las dos fuentes de la portada, y mantener la rejilla de galería con carga diferida.

### 5.4 Otros puntos de accesibilidad vistos en nuestro código

- Cabecera: los enlaces de navegación se ocultan por debajo de 900 px y solo queda el botón "Reservar"; en móvil no hay forma de llegar a Menú, Galería o Ubicación desde la cabecera (para `/s`). Considerar un menú plegable sin JavaScript (`details/summary`, como ya hizo el prototipo anterior).
- Foco visible ya definido con contorno de 3 px en acento; correcto.
- Texto alternativo: los sitios de referencia leídos (Rosetta, Contramar) tenían imágenes sin alt en el HTML. En `x-sitio.imagen` el `alt` es obligatorio por parámetro; conviene que la galería y los platos permitan alt editable.
- Aviso de privacidad: el formulario de reserva recopila nombre y teléfono. Quintonil y Máximo enlazan un aviso de privacidad en el pie. No es parte de este tema, pero conviene confirmar con quien corresponda si `/s` debe enlazar uno.

---

## 6. Bloques propuestos y datos que necesita cada uno

Estado: **hoy editable** = existe campo y el dueño lo cambia desde el panel; **parcial** = existe pero con límites; **no existe** = hay que crear campo.

| Bloque | Qué muestra | Datos que necesita | Estado hoy |
|---|---|---|---|
| **Cabecera** | Nombre o logotipo, enlaces a las secciones que existen, botón Reservar | `nombre`, `logo`, existencia de cada sección | Hoy editable. Los enlaces se ocultan si la sección está vacía (ya ocurre) |
| **Portada** | Foto grande, titular (con una palabra en cursiva dorada), subtítulo, botones Reservar y Ver menú, estado abierto/cerrado | `portada`, `eslogan`, `nombre`, estado de apertura, `reservas_activas` | Portada, eslogan y nombre editables. La palabra destacada y el subtítulo no tienen campo propio |
| **Historia** | Etiqueta, título, uno o dos párrafos, foto opcional | Título, párrafos, imagen | Solo `descripcion` (un texto). Título, párrafos e imagen **no existen** |
| **Destacados** | 3 a 6 platos en tarjetas (foto, nombre, descripción corta, precio), con modo solo texto sin foto | Productos marcados como destacados, o los primeros visibles | Hoy editable (productos destacados). Máximo 6 |
| **Carta corta** | Lista de texto con categorías y precios y enlace a la carta completa | Categorías y productos visibles | Hoy editable |
| **Galería** | 4 a 6 fotos | `GaleriaImagen` (imagen y orden) | La tabla existe y el sitio la lee; el panel que la edita está en la API (`ImagenController`). Falta confirmar que el propietario pueda subir y ordenar desde la interfaz |
| **Reserva** | Formulario, medios alternos, límites visibles | `reservas_activas`, WhatsApp, teléfono, límites de la configuración | Hoy editable; el texto introductorio no |
| **Banda de reservación** | Franja corta con llamado y botón | Texto del llamado, `reservas_activas` | Texto **no existe** (hoy fijo) |
| **Ubicación y horario** | Dirección, mapa, horarios compactos, indicaciones | Contactos (dirección), horarios, latitud y longitud | Hoy editable. "Indicaciones para llegar" **no existe** |
| **Contacto y redes** | Teléfono, WhatsApp, Instagram, Facebook | Contactos y `whatsapp_pedidos` | Hoy editable |
| **Pie** | Nombre, año, enlace a la carta, aviso de privacidad | `nombre`, enlace de privacidad | Privacidad **no existe** |

**Títulos y etiquetas de las secciones** (el "Una mesa, una historia." y similares): **no existen como datos**, y hoy están fijos en la plantilla. Para cumplir la regla de que ningún texto del diseño sea fijo, hay dos caminos: (1) campos editables por sección con valores por defecto neutros ("Nosotros", "Menú", "Galería", "Reservaciones", "Ubicación y horario"), o (2) usar solo esos rótulos neutros sin frases decorativas. El camino 2 cuesta poco y cumple la regla sin tocar el panel; el 1 es lo que permite el tono "de autor".

Reglas de contenido: ninguna sección debe mostrar una afirmación (premios, estrellas, años de experiencia) que no venga de un dato del restaurante. Las secciones sin datos se ocultan, no se rellenan.

---

## 7. Qué no pude verificar

1. **La imagen de referencia no llegó adjunta**; todo lo relacionado con ella (portada con palabra en cursiva dorada, tarjetas de platillos, banda de reservación) lo traté como intención descrita en el texto.
2. **Estética de los 14 sitios**: fondo oscuro, serifa, color de acento, tamaños y espaciados de títulos. La herramienta devuelve texto sin estilos y el navegador automatizado no estaba disponible (falta Chrome; no lo instalé). Por eso **no hay parejas tipográficas observadas, ni tamaños relativos, ni uso del dorado observado** en los sitios de referencia. Lo que aparece de tipografía y dorado es de nuestro código y de cálculos propios.
3. **Cuáles de esos sitios son realmente oscuros.** Una búsqueda de plantillas oscuras con serifa y dorado (por ejemplo https://www.framer.com/community/marketplace/templates/lemiererestaurant/) mostró solo plantillas comerciales, que no son restaurantes reales y no se usaron como referencia.
4. **Pujol, Don Julio y The Clove Club**: no abrieron (429 y errores de resolución de nombre).
5. **Pesos de archivo** de Fraunces, Playfair Display, Bodoni Moda, Instrument Serif, Newsreader y DM Sans: Fontsource no los muestra y no los descargué.
6. **Qué hacen los restaurantes cuando no tienen fotos buenas**: no hay observación; los sitios leídos siempre tenían fotos.
7. **Peso real de la portada en una prueba de red lenta**: no medí nada en el sitio en ejecución. Solo revisé el marcado y la configuración de imágenes.
8. **Que los velos del sitio B cumplan sobre fotos reales**: los contrastes sobre foto son de peor caso calculado, no de fotos del producto.
9. **Estado actual de la interfaz del panel para la galería**: leí el modelo y los controladores de la API, pero no abrí la interfaz.
10. **Aviso de privacidad y requisitos legales**: solo anoté que los sitios de referencia mexicanos enlazan uno; no es una conclusión legal.
11. No revisé el código de reservas, pedidos ni el panel más allá de lo citado.
