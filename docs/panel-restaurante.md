# Rediseño del panel del restaurante: auditoría y propuesta (Fase 1)

Estado: propuesta para aprobación. No se escribió código de producción. No se hizo `git add`, `commit` ni `push`.
Alcance: panel del dueño en `/admin/{restaurante}` (Filament 5) y el panel de servicio `/panel`. Quedan fuera el sitio público, las plantillas a–h y la sección de Plataforma (Plataforma, Restaurantes, Cuentas).

Todo lo que sigue sale de leer el código en la rama `panel-restaurante`. Donde algo no se pudo confirmar sin ejecutarlo, está marcado como "por confirmar".

---

## 0. Resumen de hallazgos (lo más importante primero)

1. **Hay dos paneles distintos, no uno.** El dueño usa `/admin/{restaurante}` (Filament: menú, horario, contactos, QR, "Mi restaurante") y además `/panel` (una app propia en Blade + JavaScript: Inicio, Reservas, Menú, Pedidos, Ajustes). Se enlazan entre sí ("Panel de servicio"), pero tienen datos, textos y reglas de rol distintos. Para un dueño novato esto es la mayor fuente de confusión. Toda la propuesta depende de la decisión D1 (qué hacer con esa dualidad).
2. **La premisa "la plantilla del sitio solo se elige en Plataforma" no es cierta hoy.** El dueño (propietario) ya puede elegir las 8 plantillas en `Mi restaurante → Diseño del menú → Plantilla del sitio` (Filament) y en `/panel → Ajustes → Sitio público` (botones A–H). Lo que falta es una **vista previa**, nombres claros (en `/panel` se muestran como "A · Minimal editorial"), y que se entienda la diferencia entre "plantilla" y "estilo visual". En Plataforma → Restaurantes solo se edita nombre, dirección corta, moneda, idioma, zona horaria, estado, logo, portada y correo del propietario; no hay selector de plantilla.
3. **Cosas que el dueño sí puede hacer pero están repartidas o escondidas:** reservas (activar, cupo, franja) solo existen en `/panel → Ajustes`; mapa (latitud/longitud) y galería de fotos solo en `/panel`; contactos completos solo en Filament; la portada y el logotipo en Filament; la portada en `/panel` pero **sin logotipo**.
4. **Subida de imágenes: base sólida, con huecos reales.** Ya se valida el tipo real de archivo, se rechaza SVG, se limita tamaño y píxeles, se reencodifica a WebP (se pierden EXIF/GPS) y los nombres son aleatorios. Falta: respetar la orientación de las fotos de celular (hoy se pueden guardar giradas), un mensaje de error en lugar de un error 500 cuando el archivo no se puede leer, y el texto de ayuda dice "máx. 5 MB" pero el límite real es 2 MB. Detalle en la sección 3.
5. **Aislamiento entre restaurantes: bien resuelto en lo central.** Alcance global por restaurante que "falla cerrado", políticas por rol, tenancy de Filament y un middleware para la API de `/panel`. Los puntos a vigilar (no son fugas confirmadas) están en la sección 4. Hay una **inconsistencia de roles** entre `/panel` y Filament para "gerente" que conviene resolver (D4).
6. **Textos:** el código usa "carta" en algunos lugares del panel (`Idioma principal de tu carta`, `orden en la carta`). La regla es "Menú". Se corrige dentro del rediseño. Las pantallas públicas (por ejemplo "Carta no disponible", "carta impresa") están cubiertas por pruebas y por tu instrucción de no tocar el sitio público; las dejo fuera salvo que me lo pidas (D9).

---

## 1. Inventario del panel actual

### 1.1 Panel Filament: `/admin/{restaurante}`

La dirección del restaurante en la URL es su "slug"; el dueño nunca debería verla nombrada así.

| Pantalla | Clase | Grupo de navegación | Orden | Quién la ve (política) | Qué edita |
|---|---|---|---|---|---|
| Inicio | `Pages\Inicio` + `ResumenRestauranteWidget` | (sin grupo) | primera | todos los miembros | Solo lectura: estado (publicado/abierto), turnos de hoy, botón "Agregar platillo" y lista de pendientes (primera categoría, platillos, WhatsApp faltante, horario, agotados, platillos sin foto, QR de mesas) |
| Platillos | `ProductoResource` | Menú | 10 | superadmin, propietario, editor, gerente | Nombre y descripción por idioma, precio, categoría, fotografía, disponible, destacado, precio promocional, etiquetas dietéticas, visible, orden, variantes (nombre por idioma, precio, orden, disponible). Tabla con interruptores de disponible y visible, filtro por categoría, filtro de agotados, papelera con deshacer |
| Categorías | `CategoriaResource` | Menú | 20 | superadmin, propietario, editor (**gerente no**) | Nombre y descripción por idioma, visible, orden |
| Horario | `HorarioResource` | Restaurante | 30 | superadmin, propietario, editor | Un registro por turno: día, cerrado, apertura, cierre (cierre menor que apertura = termina al día siguiente) |
| Códigos QR | `CodigoQrResource` | Restaurante | 40 | superadmin, propietario (todo); editor (ver, crear, editar); gerente no | Nombre, tipo (QR o NFC), mesa, código corto, activo. Descarga SVG / PNG / hoja imprimible (`/admin/codigos-qr/{id}/descargar/{formato}`) |
| Contactos | `ContactoResource` | Restaurante | 50 | superadmin, propietario, editor | Tipo (teléfono, WhatsApp, correo, dirección, Instagram, Facebook, TikTok, sitio web, otro), etiqueta, valor validado, orden, visible |
| Panel de servicio | enlace manual | Restaurante | 5 | todos | Lleva a `/panel` |
| Mi restaurante | `Pages\Tenancy\EditRestauranteProfile` | Restaurante | 60 | superadmin y propietario (`update` de la política) | Ver 1.3 |
| Equipo | `UserResource` | Administración | 55 | según política de usuarios | Lista del equipo del restaurante |
| Invitaciones | `InvitacionResource` | Administración | 60 | propietario / gerente (por confirmar en `InvitacionPolicy`) | Invitar por correo con rol |
| Historial de cambios | `HistorialCambioResource` | Administración | 90 | por confirmar | Solo lectura (auditoría) |
| Restaurantes / Cuentas | `RestauranteResource`, `UserResource` (superadmin) | Plataforma | 80 / 55 | solo superadmin | Fuera de alcance |
| Barra inferior en celular | `filament/hooks/barra-inferior` | (hook) | | todos | Inicio, Platillos, Categorías, Horario, "Más" |
| Botón "Ver mi menú" | `filament/hooks/ver-menu-publico` | (cabecera) | | todos | Abre el menú público en otra pestaña |

Otros: inicio de sesión propio con límite de intentos (`Pages\Auth\Login`), dos factores obligatorio para superadmin y propietarios (`ExigeDosFactores`), recuperación de contraseña, perfil de cuenta (Filament `->profile()`), `Pages\Componentes` (muestra de diseño, solo local).

### 1.2 Panel de servicio: `/panel` (Blade + JavaScript, API `/api/v1/panel/{restaurante}/...`)

| Módulo | Rol que lo ve (`PermisosPanel`) | Qué hace |
|---|---|---|
| Inicio | propietario, gerente, editor, recepción | Resumen del día |
| Reservas | propietario, gerente, editor, recepción | Crear, cambiar estado, borrar reservas |
| Menú | propietario, gerente, editor | Ver menú, crear platillo en una categoría, editar, duplicar, borrar, subir o quitar foto del platillo |
| Pedidos | propietario, gerente, editor, cocina | Tablero por estado, crear pedido, avanzar estado |
| Ajustes | propietario, gerente | Nombre, eslogan, descripción, WhatsApp, teléfono, dirección, Instagram, Facebook, latitud/longitud, **plantilla A–H**, portada, galería (máx. 12), horario por día (un turno), **reservas (activar, cupo, franja 30/60/90/120)**, equipo (invitar, revocar, cambiar rol) |

### 1.3 Mi restaurante (Filament) campo por campo

Secciones: "Diseño del menú", "Mi restaurante", "Pedidos por WhatsApp", "Idiomas adicionales" (plegada).
Campos: estilo visual (4 temas), plantilla del sitio (8), color principal, fotografía de portada, logotipo, frase corta, descripción breve, nombre, moneda, idioma principal, zona horaria, activar pedidos por WhatsApp, WhatsApp para pedidos, mostrar platillos agotados, idiomas activos (con orden).

### 1.4 Qué existe hoy para cada necesidad

| Necesidad | Filament `/admin` | `/panel` | Comentario |
|---|---|---|---|
| Nombre | Mi restaurante | Ajustes | Duplicado |
| Eslogan | Mi restaurante ("Frase corta o especialidad") | Ajustes | Duplicado |
| Descripción | Mi restaurante ("Descripción breve", 500) | Ajustes | Duplicado |
| Logotipo | Mi restaurante | No | Solo Filament |
| Fotografía de portada | Mi restaurante | Ajustes | Duplicado; recorte distinto (ver sección 3) |
| Galería de fotos | No | Ajustes | Solo `/panel`; hoy no hay resource de Filament para `GaleriaImagen` |
| Fotos de platillos | Platillos | Menú | Duplicado; recorte distinto |
| Estilo visual (4 temas) | Mi restaurante | No | Solo Filament |
| Plantilla del sitio (a–h) | Mi restaurante | Ajustes | Duplicado, sin vista previa |
| Color principal | Mi restaurante | No | Solo Filament |
| Horario | Horario (un registro por turno, varios turnos) | Ajustes (un turno por día; si hay varios dice "edítalo en la administración avanzada") | Dos modelos mentales |
| Teléfono | Contactos | Ajustes | Duplicado |
| WhatsApp (contacto) | Contactos (tipo WhatsApp) | Ajustes ("whatsapp") **guarda en `whatsapp_pedidos`**, no en Contactos | Confuso: son dos datos distintos con el mismo nombre |
| Dirección | Contactos | Ajustes | Duplicado |
| Mapa (lat/long) | No | Ajustes | Solo `/panel`, y pide coordenadas a mano |
| Redes (Instagram, Facebook, TikTok, web) | Contactos | Ajustes (solo Instagram y Facebook) | TikTok y web solo Filament |
| Reservas: activar, cupo, franja | No | Ajustes | Solo `/panel` |
| Pedidos por WhatsApp (activar, número) | Mi restaurante | No (el número se llama "WhatsApp") | |
| Mostrar agotados | Mi restaurante | No | |
| Categorías | Categorías | Solo se ven dentro de Menú | Crear/editar solo Filament |
| Platillos | Platillos | Menú | Duplicado |
| Variantes | Platillos (repetidor) | No | Solo Filament |
| Etiquetas dietéticas | Platillos (opciones adicionales) | No | Solo Filament |
| Platillo destacado | Platillos ("Destacar como recomendado") | No | Solo Filament |
| QR y mesas | Códigos QR (la mesa es un campo de cada QR) | No | Solo Filament |
| Idiomas | Mi restaurante | No | |
| Equipo e invitaciones | Equipo, Invitaciones | Ajustes | Duplicado |

---

## 2. Qué no puede hacer el dueño y debería poder

### 2.1 Huecos para el dueño

| Hueco | Hoy | Propuesta |
|---|---|---|
| Ver cómo queda el sitio/menú antes de cambiar de plantilla o color | No hay vista previa; el cambio se aplica al instante | Vista previa en la propia pantalla de Apariencia (ver 2.2) |
| Un solo lugar para editar su información | Repartida en 2 paneles y 5 pantallas | Sección "Información" única |
| Elegir mapa sin coordenadas | Debe escribir latitud y longitud | Campo "Enlace de Google Maps" o "Dirección" y, si es posible, extraer coordenadas (D6) |
| Reservas fuera de `/panel` | Solo allí | Sección "Pedidos y reservas" |
| Galería desde Filament | No existe | Sección Apariencia → Fotos del local |
| Saber qué falta para publicar | Lista de pendientes en Inicio, no ordenada ni con avance | "Qué falta configurar" con porcentaje y orden fijo |
| Cambiar idioma y moneda con consecuencias claras | Campos sueltos | Textos de ayuda (ver sección 5) |
| Probar el pedido por WhatsApp | No | Botón "Enviarme un mensaje de prueba" (idea opcional, D6) |
| Agrupar horario por día con varios turnos en una vista | Solo lista de turnos | Editor semanal (7 filas, "agregar otro turno") |
| Quitar un contacto sin borrarlo | Existe "visible" | Se conserva |

### 2.2 Qué hace hoy solo la plataforma

Solo el superadmin puede: crear restaurantes (con correo del propietario), cambiar la dirección corta (slug), suspender (`estado`), ver todas las cuentas. Todo eso debe seguir así. La dirección corta no debe poder cambiarla el dueño porque rompe QR impresos y enlaces ya compartidos (los QR usan `/q/{código}` y no dependen del slug, pero los enlaces directos y `Ver mi menú` sí).

### 2.3 Que el dueño elija la plantilla (con vista previa): pros y contras

Hoy ya puede elegir (ver hallazgo 2). La decisión real es si se deja así y se mejora, o se quita.

Pros de mantenerlo y mejorarlo:
- Es el cambio de mayor impacto visual y lo que más "se siente suyo".
- Evita depender de soporte para algo reversible.
- La arquitectura ya lo permite: `sitio_plantilla` está validado contra la lista cerrada `Catalogos::plantillasSitio()` en las dos rutas.

Contras y riesgos:
- Una elección mala (por ejemplo, oscuro con fotos muy claras) puede hacer ver peor el negocio y el dueño no sabe por qué.
- Cada plantilla puede esperar contenido distinto (portada, galería, platillos con foto). Plantilla sin foto = sitio feo. La vista previa debe usar el contenido real del restaurante.
- Las plantillas e–h traen sus propios colores y tipografías: el "estilo visual" y el "color principal" **no les aplican** (lo dice la ayuda de "Mi restaurante" solo para Cafetería; hay que verificar cuáles de e–h lo ignoran, por confirmar). Si no se explica, el dueño cambia el color y no pasa nada.
- Ocho opciones abruman. Un dueño novato decide mejor con 3 a 4 recomendaciones según tipo de negocio.
- Pruebas: varias pruebas verifican `array_keys(Catalogos::plantillasSitio())` y los nombres; cualquier cambio de nombres las rompe.

Qué se necesitaría:
1. Vista previa en vivo: `iframe` del sitio real (`/{slug}?vista=plantilla-x`) con un parámetro que solo acepta el propietario autenticado y que **no se guarda** hasta confirmar. Requiere un cambio mínimo en `SitioPublicoController` (aceptar un parámetro de vista previa autorizado). Se mueve al sitio público, así que necesito tu visto bueno (D2). Alternativa más barata: una imagen de muestra por plantilla, estática.
2. Descripciones en lenguaje de dueño ("Para pizzerías y trattorias") en vez de A–H.
3. Un botón "Probar esta plantilla" y otro "Usar esta plantilla". Mensaje: "Tus fotos y platillos se conservan; solo cambia el diseño".
4. Si el estilo visual y el color no aplican a la plantilla elegida, ocultarlos o marcarlos con "No aplica a este diseño".
5. Registro en historial de cambios (ya existe `Auditable`).

---

## 3. Seguridad de las subidas de imágenes

Se revisó `WebpImageService`, `ImagenRestauranteUpload` (Filament), `ImagenController` (`/panel`), el trait `ProtegeImagenesDelRestaurante` y las pruebas existentes.

### 3.1 Qué ya está

| Control | Estado | Dónde |
|---|---|---|
| Tipo real del archivo (bytes mágicos, `finfo`) | Sí; solo JPEG, PNG, WebP | `WebpImageService::validateImage`, `getRealMimeType` |
| Rechazo de SVG | Sí, por extensión, por los primeros 1024 bytes y por tipo real | `validateImage` (3 capas) |
| GIF y otros formatos | Rechazados (prueba "gif renombrado a png") | `ALLOWED_MIMES` |
| Tamaño máximo antes de decodificar | Sí, `config('menuonl.imagenes.max_bytes')`, por defecto 2 MB | `validateImage` |
| Dimensiones máximas | Sí, 4096 px por lado, medido con `getimagesize` antes de decodificar | `validateImage` |
| Se reencodifica siempre | Sí: se decodifica con GD, se copia a lienzo nuevo y se guarda como WebP. Esto elimina EXIF/GPS, comentarios y cargas ocultas | `processAndStore`, `storeCroppedForRestaurante` |
| Nombre de archivo generado por el servidor | Sí, 40 caracteres aleatorios + `.webp`; el nombre original no se usa | `Str::random(40)` |
| Ubicación | `storage/app/public/restaurantes/{id}/{carpeta}/`; carpeta validada contra lista cerrada | `storeForRestaurante` |
| No se guarda el original | Sí (el archivo temporal de Livewire no se publica) | |
| La ruta guardada en la base solo puede ser del propio restaurante | Sí, a prueba de manipulación del formulario | `ProtegeImagenesDelRestaurante`, `preventFilePathTampering` |
| Borrado de la imagen anterior | Sí, solo dentro del directorio del restaurante | trait + `deleteFromRestaurante` |
| Límite de fotos de galería | Sí (12) | `ImagenController::galeria` |
| Límite de peticiones | Sí en `/panel` (`throttle:panel-api`) | `routes/api.php` |
| Pruebas | Hay 15 pruebas de desafío del servicio, 9 de imágenes del panel y 16 de aislamiento de archivos | `tests/Feature` |

### 3.2 Qué falta o está flojo (ordenado por impacto)

1. **Orientación de fotos de celular (EXIF).** GD no aplica la rotación EXIF al decodificar, y al borrar los metadatos se pierde la información. Una foto vertical tomada con el celular puede quedar **acostada o boca abajo** en el menú. Es el fallo que más va a notar un dueño. Corrección: leer la orientación antes de reencodificar y rotar (cuidando que la extensión `exif` esté disponible; si no, se hace con ayuda de otra lectura del encabezado JPEG).
2. **Mensaje del límite incorrecto.** La ayuda dice "máx. 5 MB" (`ImagenRestauranteUpload`, `EditRestauranteProfile`), pero el límite configurado es 2 MB. Un dueño con una foto de 3 MB recibirá un rechazo contradictorio. Decidir el límite real (D5) y mostrar un solo valor leído de la configuración. Además, las fotos de celular modernas rara vez bajan de 2 MB: lo más amable es **aceptar hasta 10 MB y reducir en el servidor**, porque el servidor ya reencodifica.
3. **Archivo ilegible = error 500.** Si el archivo pasa las validaciones pero GD no lo puede decodificar (por ejemplo un WebP animado o dañado), `createGdResource` lanza `RuntimeException`, que no es una `ValidationException`, y el usuario ve un error técnico. Debe convertirse en el mensaje "No pudimos leer esta foto. Prueba con otra o toma una captura de pantalla".
4. **Respaldo permisivo de decodificación.** Si el cargador específico falla, se cae a `imagecreatefromstring`, que acepta más formatos que los tres permitidos. Como el tipo real ya se validó antes, el riesgo es bajo, pero conviene quitar ese respaldo para que "solo JPG/PNG/WebP" sea literal.
5. **Límite de píxeles totales, no solo por lado.** 4096 × 4096 son 16,7 millones de píxeles, unos 64 MB en memoria con GD, por cada subida simultánea. Propongo un tope de ~16 MP totales y comprobar que cabe en `memory_limit` antes de decodificar, para que varias subidas a la vez no tumben el servidor.
6. **Dos caminos con recortes distintos.** Filament usa `processAndStore` (caja de 1200 × 1200, sin recorte, con transparencia) y `/panel` usa `storeCroppedForRestaurante` (recorte fijo 4:5 o 16:9, tres anchos, sin transparencia). Resultado: la misma portada se ve distinta según dónde se subió, y las fotos subidas en Filament no tienen copias responsivas (`srcset`). Unificar en el camino con recorte y copias (D3).
7. **Transparencia en el camino con recorte.** `storeCroppedForRestaurante` no preserva el canal alfa (un PNG transparente queda con fondo negro). Para fotos de platillo es irrelevante; para el logotipo sí, y el logotipo va por el otro camino. Al unificar hay que mantener alfa solo para el logotipo.
8. **Perfil de color (ICC) descartado.** Efecto secundario de reencodificar: colores ligeramente apagados en fotos con perfiles anchos. Aceptable, pero conviene convertir a sRGB si hay perfil.
9. **Límite de subida de Livewire.** No hay `config/livewire.php` publicado, así que rige el valor por defecto de Livewire (12 MB, regla `file`). Está bien como primera barrera; conviene fijarlo explícitamente a un valor coherente con D5.
10. **Sin análisis antivirus ni limpieza de archivos huérfanos.** El reencodificado ya neutraliza cargas ocultas; no recomiendo antivirus. Sí un comando programado que borre temporales viejos y archivos sin referencia (hoy, al borrar un platillo la foto se limpia solo si el modelo lo hace; por confirmar el borrado suave).
11. **Cabeceras del servidor.** Verificar en producción que `/storage/restaurantes/*` se sirve con `X-Content-Type-Options: nosniff` y que no se ejecuta nada en esa carpeta (por confirmar en la configuración del hosting; está en `docs/07-checklist-despliegue.md`).

### 3.3 Endurecimiento propuesto (un solo servicio para todas las rutas)

- Un único método público `WebpImageService::procesar(archivo, restauranteId, carpeta, perfil)` con perfiles: `logotipo` (alfa, caja 800 px), `portada` (16:9, tres anchos), `platillo` (4:5, tres anchos), `galeria` (4:5). Filament y `/panel` lo llaman sin lógica propia.
- Orden fijo: tamaño en bytes → extensión y bytes mágicos → dimensiones y píxeles totales → decodificar → rotar por EXIF → recortar/redimensionar → sRGB → guardar WebP con nombre aleatorio.
- Todos los fallos devuelven `ValidationException` con textos de dueño.
- Mensajes únicos, sin jerga ("Esta foto pesa demasiado. El máximo es 10 MB.", "Usa una foto JPG, PNG o WebP.").
- Pruebas nuevas: foto vertical con EXIF de rotación, WebP animado, JPEG truncado, imagen de ~16 MP, subida simultánea, PNG transparente como logotipo.

No requiere migración ni cambia permisos: es un refuerzo interno.

---

## 4. Aislamiento entre restaurantes

### 4.1 Cómo se garantiza hoy

| Capa | Mecanismo | Dónde |
|---|---|---|
| Qué restaurantes ve un usuario | `User::getTenants` y `canAccessTenant` (membresía activa en `restaurante_usuario`; superadmin ve todos) | `app/Models/User.php` |
| Filtro automático de datos | Alcance global `RestauranteScope` vía el trait `BelongsToRestaurante`. En una petición del panel sin restaurante resuelto devuelve **cero filas** (falla cerrado) | `app/Models/Scopes/RestauranteScope.php` |
| Modelos con el alcance | Categoria, CodigoQr, Contacto, GaleriaImagen, HistorialCambio, Horario, Invitacion, Pedido, Producto, Reserva, RestauranteIdioma | `grep BelongsToRestaurante` |
| Permisos por rol | Matriz `MatrizPolicy`: sin rol en ese restaurante = denegado | `app/Policies/*` |
| API de `/panel` | `ContextoRestaurantePanel` fija el restaurante **antes** del enlace de modelos; no miembro o cuenta inactiva = 404 igual que un restaurante inexistente. `ModuloPanel` aplica el módulo por rol | `app/Http/Middleware` |
| Archivos | La ruta guardada debe empezar por `restaurantes/{id}/` y no admite `..` | `rutaPerteneceAlRestaurante` + trait |
| Equipo | `UserResource` filtra con `whereHas('restaurantes', ...)` | `UserResource::getEloquentQuery` |
| Descarga de QR | `Gate::authorize('view')` más verificación de pertenencia | `QrDownloadController` |
| Pruebas | `PanelTenancyTest`, `AislamientoPanelTest`, `AislamientoModelosTest`, `AislamientoArchivosTest`, `RestauranteMultiTenantIsolationTest`, `QrNfcMultiTenantAdversarialTest` y otras | `tests/Feature` |

### 4.2 Qué no tiene la protección automática (a vigilar, no son fugas confirmadas)

1. **`Etiqueta`** no tiene `restaurante_id`: es un catálogo global compartido (vegano, picante...). Es intencional; solo hay que impedir que un dueño las edite (no hay pantalla para ello hoy).
2. **`ProductoVariante`, `ProductoTraduccion`, `CategoriaTraduccion`, `VarianteTraduccion`** no llevan el alcance global; se protegen por la política del padre (`restauranteDe`) y por llegar siempre a través de un `Producto` o `Categoria` ya filtrado. Es correcto mientras no se creen pantallas que los consulten directamente. **Regla para el rediseño:** ninguna pantalla nueva lista estos modelos sin pasar por el padre.
3. **`Restaurante`** no tiene el alcance (es el propio restaurante). Se protege con `RestaurantePolicy` (propietario edita; editor solo ve; **gerente no aparece en la matriz**) y con `canAccessTenant`.
4. **Consultas fuera del panel** (rutas públicas, consola, colas): `resolveRestauranteId()` devuelve `null` y no filtra. Es correcto para el menú público (que filtra por `slug`), pero cualquier controlador nuevo que use un modelo con alcance **fuera** de Filament y de `ContextoRestaurantePanel` quedaría sin filtro. Hoy lo cubren las políticas en la ruta de descarga de QR. **Regla para el rediseño:** toda ruta nueva autenticada debe pasar por `ContextoRestaurantePanel` o usar `Gate::authorize` sobre un registro.
5. **Inconsistencia de roles (D4):** `PermisosPanel` da el módulo "ajustes" y "menú" al **gerente**, pero en Filament el gerente no tiene política para Categorías, Horarios, Contactos, Códigos QR ni Mi restaurante (solo Productos, Galería, Pedidos, Reservas). En `/panel` un gerente puede cambiar plantilla, portada y datos del local; en Filament no. Hay que decidir qué puede hacer cada rol antes de unificar.
6. **`/panel` aplica `ModuloPanel` pero algunos endpoints de ajustes (nombre, plantilla, horario, portada) no llaman a `Gate::authorize`**; dependen solo del módulo "ajustes". Hoy es coherente (propietario y gerente), pero si se separan permisos habrá que añadir `Gate`.
7. **`HistorialCambio` y `Invitacion`** sí tienen alcance; la política de lectura del historial queda por confirmar.

No encontré recursos de Filament sin alcance de restaurante fuera de los de Plataforma, que son intencionalmente globales y restringidos a superadmin.

---

## 5. Propuesta de navegación para un dueño novato

### 5.1 Principios

- Siete elementos como máximo, con verbos y palabras del negocio.
- Cada pantalla responde una pregunta: "¿Qué ve mi cliente?", "¿Cuándo y cómo me contactan?".
- Guardado explícito con un botón fijo abajo en celular. Textos de error que dicen cómo arreglarlo.
- Valores por defecto razonables para que un restaurante nuevo se pueda publicar con lo mínimo.
- La barra inferior en celular: **Inicio, Mi menú, Pedidos y reservas, Apariencia, Más**.

### 5.2 Árbol de navegación

```
INICIO                         (resumen + "Qué falta configurar")
MI MENÚ
   Platillos
   Categorías
APARIENCIA
   Diseño del sitio            (plantilla, estilo, color; vista previa)
   Logotipo y fotos            (logotipo, portada, galería)
INFORMACIÓN
   Datos del restaurante       (nombre, frase, descripción)
   Horario
   Contacto y ubicación        (teléfono, WhatsApp, dirección, mapa, redes)
PEDIDOS Y RESERVAS
   Pedidos por WhatsApp
   Reservas
   Pedidos y reservas de hoy   (enlace al tablero en vivo)
QR Y MESAS
CUENTA
   Equipo
   Idiomas y moneda
   Mi cuenta y seguridad
   Historial de cambios
[Ver mi menú]                  (botón siempre visible en la cabecera)
```

La sección Plataforma queda tal cual y solo la ve el superadmin.

### 5.3 Maquetas en texto

**Inicio**
```
Hola, [nombre]. Tu menú está publicado.            [Ver mi menú]
Hoy: Abierto ahora (13:00 a 22:00)

Tu restaurante está al 70%
[#######---]  Te faltan 3 pasos
  1. Agrega tu horario                    [Hacerlo]       importante
  2. Sube la portada                      [Hacerlo]
  3. Crea los QR de tus mesas             [Hacerlo]
  Listo: nombre, 12 platillos, WhatsApp

Atención
  Tienes 2 platillos agotados             [Revisar]
  4 platillos sin foto. Con foto se piden más   [Agregar fotos]

Accesos rápidos:  [Agregar platillo] [Cambiar plantilla] [Descargar QR]
```
Si no hay nada pendiente: "Todo en orden. Tu menú está completo."

**Mi menú → Platillos** (lista con buscador, foto en miniatura, nombre, categoría, precio, interruptores "Disponible" y "Visible"; botón fijo "Agregar platillo"). Formulario en este orden: Nombre, Foto, Precio, Categoría, Descripción (opcional); más abajo, plegado: "Más opciones" (precio promocional, etiquetas, destacado, variantes, orden). Orden por arrastrar en lugar de número (D7).

**Mi menú → Categorías**: lista que se reordena arrastrando. Formulario: Nombre, Descripción (opcional), Mostrar en el menú.

**Apariencia → Diseño del sitio**
```
Elige cómo se ve tu sitio y tu menú
[Vista previa en celular]  [ Sitio | Menú ]          (iframe)

Diseño
 ( ) Pizzería            Papel crema y rojo tomate...   [Probar]
 ( ) Bar                 ...
 (8 tarjetas con foto de muestra; "Recomendado para: pizzerías")
Estilo del menú         (solo si el diseño elegido lo usa)
 Cálido | Editorial oscuro | Rústico | Clásico
Color principal         [selector]  [Usar el original]
                                      [Guardar y publicar]
```

**Apariencia → Logotipo y fotos**: tres tarjetas con vista previa y botón de cámara (en celular abre la cámara o la galería): Logotipo, Portada, Fotos del local (cuadrícula, hasta 12, arrastrar para ordenar). Cada una muestra el tamaño recomendado en palabras ("Foto horizontal, de preferencia 1600 px de ancho").

**Información → Datos del restaurante**: Nombre, Frase corta, Descripción. Contador de caracteres. Vista previa en el encabezado.

**Información → Horario**: siete filas (Lunes a Domingo) con interruptor "Abierto", hora de apertura y cierre, y "Agregar otro turno". Atajo "Copiar a todos los días" y "Copiar de lunes a viernes". Mensaje claro si el cierre es menor que la apertura: "Cierra después de medianoche (al día siguiente)".

**Información → Contacto y ubicación**: Teléfono, WhatsApp, Dirección, Enlace de Google Maps (opcional), Instagram, Facebook, TikTok, Sitio web. Cada campo con ejemplo. Un único registro por tipo; lo avanzado (varios números, etiquetas, orden) en "Más contactos".

**Pedidos y reservas → Pedidos por WhatsApp**: interruptor "Recibir pedidos por WhatsApp"; "Número de WhatsApp que recibe los pedidos" con selector de país y número; "Mostrar platillos agotados". Botón "Enviarme un mensaje de prueba" (opcional, D6).

**Pedidos y reservas → Reservas**: interruptor "Aceptar reservas desde mi sitio"; "¿Cuántas personas puedes recibir al mismo tiempo?" (cupo); "Duración de cada reserva" (30 min, 1 h, 1 h 30, 2 h); "Anticipación mínima" informativa. Si está activa y no hay horario, aviso.

**QR y mesas**: botón "Crear QR de mi menú" (uno general) y "Crear QR de mesas": pide "¿Cuántas mesas?" y los genera de una vez (Mesa 1 a Mesa N). Lista con "Descargar", "Imprimir hoja" y "Desactivar". El código corto se genera solo y queda en "Opciones avanzadas".

**Cuenta → Equipo**: lista de personas con rol en palabras ("Puede cambiar todo", "Puede editar el menú", "Solo reservas", "Solo cocina") y "Invitar persona".

### 5.4 Campos por sección: orden, ayuda, valores por defecto y validaciones

| Sección | Campo (texto visible) | Ayuda | Por defecto | Validación | Si falta |
|---|---|---|---|---|---|
| Datos | Nombre del restaurante | "Así aparece en tu sitio y en tus pedidos." | (el del alta) | Obligatorio, máx. 150 | No se puede guardar |
| Datos | Frase corta | "Por ejemplo: Cocina italiana de leña." | vacío | Máx. 120 | No se muestra la línea |
| Datos | Descripción | "Cuenta en pocas líneas qué hace especial tu cocina." | vacío | Máx. 500 | Se oculta el bloque |
| Logotipo | Logotipo | "Opcional. Mejor con fondo transparente (PNG)." | ninguno | JPG/PNG/WebP, máx. 10 MB (D5) | Se muestra el nombre con letra grande |
| Portada | Portada | "Una foto horizontal de tu comida o tu local." | ninguna | igual | El sitio usa un fondo de color; aviso en Inicio |
| Fotos | Fotos del local | "Hasta 12. Se muestran en la galería." | ninguna | igual, máx. 12 | Se oculta la galería |
| Diseño | Diseño del sitio | "Cambia cómo se ve; tus platillos y fotos no se pierden." | el actual (por defecto "Minimal editorial") | Lista cerrada | |
| Diseño | Estilo del menú | "Colores y letras de tu menú." | Cálido | Lista cerrada | |
| Diseño | Color principal | "Se usa en botones y detalles. Vacío = color original." | vacío | Hex de 6 dígitos | Color del estilo |
| Horario | Abierto / apertura / cierre | "Si cierras después de medianoche, pon la hora del día siguiente." | Cerrado | Hora HH:MM; abierto exige ambas horas | Sin horario no se muestra "Abierto ahora" y se avisa en Inicio |
| Contacto | Teléfono | "Con lada, para que te llamen." | vacío | Formato de teléfono | Se oculta |
| Contacto | WhatsApp | "Para que te escriban." | vacío | 8 a 20 dígitos | Se oculta |
| Contacto | Dirección | "Calle, número y colonia." | vacío | Máx. 255 | Se oculta el bloque de ubicación |
| Contacto | Enlace de Google Maps | "Abre Google Maps, busca tu local, toca Compartir y pega el enlace." | vacío | URL de Google Maps; se extrae latitud y longitud (D6) | Sin mapa, solo dirección |
| Contacto | Redes | "Pega el enlace de tu perfil." | vacío | URL https (regla `ValorContactoSeguro`) | Se ocultan |
| Pedidos | Recibir pedidos por WhatsApp | "Tus clientes arman el pedido y te llega listo a WhatsApp." | Apagado | Si está activo, exige número | Aviso en Inicio, no se activa sin número |
| Pedidos | Número de WhatsApp | "Con código de país, solo números. México: 52 y tu número." | vacío | 8 a 20 dígitos | |
| Pedidos | Mostrar platillos agotados | "Si lo apagas, desaparecen del menú mientras no hay." | Encendido | | |
| Reservas | Aceptar reservas | "Tus clientes reservan desde tu sitio." | Encendido (hoy) | | Si no hay horario, aviso |
| Reservas | Cupo | "Personas que puedes recibir a la vez." | 30 | Entero 1 a 500 | |
| Reservas | Duración de cada reserva | | 1 hora | 30, 60, 90 o 120 | |
| QR | Cantidad de mesas | "Crea un QR por mesa de una vez." | | Entero 1 a 100 | Aviso en Inicio si no hay QR |
| Cuenta | Moneda | "Se usa en todos tus precios." | MXN | Lista cerrada | |
| Cuenta | Idioma principal | "Idioma en que escribes tu menú." | Español | Lista cerrada | |
| Cuenta | Idiomas adicionales | "Solo si recibes turistas." | ninguno | Sin repetidos | |
| Cuenta | Zona horaria | "Define cuándo estás abierto." | México | Lista cerrada | |

Palabras que no deben verse: slug, tema, plantilla_sitio, tenant, módulo, `pedidos_activos`. En su lugar: "dirección de tu menú", "estilo", "diseño del sitio".

### 5.5 Cómo se ve cuando falta información

- Inicio calcula el avance con una lista fija (nombre, categoría, platillo, horario, portada, contacto, QR; WhatsApp solo si los pedidos están activos).
- Cada pantalla vacía tiene un mensaje de ayuda, una acción principal y ningún botón inactivo sin explicación ("Aún no tienes platillos. Agrega el primero").
- El menú público nunca muestra bloques vacíos (ya es el comportamiento actual para la mayoría de campos; se verificará por plantilla).

---

## 6. Tabla campo por campo: dónde está hoy y dónde quedaría

| Campo | Hoy | Quedaría |
|---|---|---|
| Nombre | Mi restaurante (Filament) / Ajustes (`/panel`) | Información → Datos del restaurante |
| Frase corta (eslogan) | idem | Información → Datos |
| Descripción | idem | Información → Datos |
| Logotipo | Mi restaurante | Apariencia → Logotipo y fotos |
| Portada | Mi restaurante / Ajustes | Apariencia → Logotipo y fotos |
| Galería | Ajustes (`/panel`) | Apariencia → Logotipo y fotos |
| Foto de platillo | Platillos / Menú (`/panel`) | Mi menú → Platillos |
| Estilo visual (4 temas) | Mi restaurante | Apariencia → Diseño del sitio |
| Plantilla (a–h) | Mi restaurante / Ajustes | Apariencia → Diseño del sitio (con vista previa) |
| Color principal | Mi restaurante | Apariencia → Diseño del sitio |
| Horario | Horario / Ajustes | Información → Horario (editor semanal) |
| Teléfono, dirección, redes | Contactos / Ajustes | Información → Contacto y ubicación |
| WhatsApp (contacto) | Contactos / Ajustes (mezclado con pedidos) | Información → Contacto y ubicación |
| Mapa | Ajustes (latitud, longitud) | Información → Contacto y ubicación (enlace de Google Maps) |
| Reservas activar / cupo / franja | Ajustes (`/panel`) | Pedidos y reservas → Reservas |
| Pedidos por WhatsApp activar | Mi restaurante | Pedidos y reservas → Pedidos por WhatsApp |
| Número de WhatsApp para pedidos | Mi restaurante | Pedidos y reservas → Pedidos por WhatsApp |
| Mostrar agotados | Mi restaurante | Pedidos y reservas → Pedidos por WhatsApp (o Mi menú, D8) |
| Categorías | Categorías | Mi menú → Categorías |
| Platillos | Platillos | Mi menú → Platillos |
| Variantes | Platillos (repetidor) | Mi menú → Platillos → Más opciones |
| Etiquetas | Platillos | Mi menú → Platillos → Más opciones |
| Destacado | Platillos | Mi menú → Platillos → Más opciones |
| QR y mesas | Códigos QR | QR y mesas |
| Moneda, idioma, zona horaria | Mi restaurante | Cuenta → Idiomas y moneda |
| Idiomas adicionales | Mi restaurante | Cuenta → Idiomas y moneda |
| Equipo, invitaciones | Equipo, Invitaciones / Ajustes | Cuenta → Equipo |
| Historial de cambios | Administración | Cuenta → Historial de cambios |
| Dirección corta (slug), estado | Plataforma | Sin cambio (solo superadmin) |

---

## 7. Qué cambiaría en el código y pruebas afectadas

### 7.1 Código

| Área | Cambio |
|---|---|
| `AdminPanelProvider` | Nuevos grupos de navegación; quitar el enlace "Panel de servicio" si se unifica (D1); registrar páginas nuevas |
| Páginas nuevas (`app/Filament/Pages/`) | `Apariencia\DisenoSitio`, `Apariencia\LogotipoYFotos`, `Informacion\DatosRestaurante`, `Informacion\ContactoUbicacion`, `Pedidos\PedidosWhatsapp`, `Pedidos\Reservas`, `Cuenta\IdiomasMoneda`. Cada una con permisos propios |
| `EditRestauranteProfile` | Se divide y se reduce o se elimina; su `canView` se traslada a las páginas nuevas |
| Recursos | `HorarioResource` pasa a editor semanal (página con repetidor); `ContactoResource` se oculta de la navegación pero se conserva ("Más contactos"); `CodigoQrResource` gana la acción "Crear QR de mesas"; `ProductoResource` y `CategoriaResource` cambian de grupo, de orden y de textos |
| `ResumenRestauranteWidget` | Reescribir `pendientes()` como lista fija con avance y enlaces a las páginas nuevas; vista Blade nueva |
| `barra-inferior` | Cambiar las 4 opciones |
| `ImagenRestauranteUpload` y `WebpImageService` | Endurecimiento de la sección 3; unificar caminos |
| Vista previa | Un componente Blade con `iframe` y, si se aprueba D2, un parámetro de vista previa autorizado en el controlador del sitio |
| `Catalogos` | Añadir descripciones para dueño y "recomendado para" por plantilla; ya modificado en el árbol de trabajo actual (hay cambios sin confirmar de la plantilla H), así que se hará sobre esa base |
| Traducciones | No existe `lang/es/panel.php`. Los textos están escritos dentro de las clases PHP. Propongo `lang/es/panel.php` solo para los textos nuevos del panel (necesito tu visto bueno para el archivo nuevo; sigue la regla de no crear carpetas base) |
| `PermisosPanel` y políticas | Solo si se resuelve D4 (y eso exige tu aprobación expresa) |
| `/panel` | Según D1: mantenerlo solo para el trabajo en vivo (reservas, pedidos) o retirarlo de ajustes |

No se requiere migración para el diseño propuesto, salvo que se aprueben dos extras opcionales: guardar un enlace de mapa (hoy hay latitud/longitud, bastaría extraerlas), y "inicio de pasos completados" (se calcula, no se guarda). Si algo requiere una migración, me detengo y pregunto.

### 7.2 Pruebas que se verían afectadas

| Prueba | Por qué |
|---|---|
| `PerfilPlantillaSitioTest` | Lee la pantalla "Mi restaurante" y busca los nombres de plantilla |
| `FilamentM4ChallengerTest`, `AislamientoArchivosTest` | Usan `EditRestauranteProfile` (formulario y subidas) |
| `UxUiAccesibilidadTest` | Comprueba grupos de navegación ("Restaurante", "Administración"), textos de Inicio ("Lo que necesita tu atención hoy en ..."), ayuda del formulario de platillo y la barra inferior |
| `PanelAjustesTest` | Prueba la API de ajustes; cambia solo si se mueve la lógica |
| `PanelTenancyTest`, `AislamientoPanelTest`, `PanelRolesOperativosTest`, `FilamentAdminAdversarialTest` | Rutas `/admin/{slug}/...` y permisos por rol; cambian si cambian rutas o roles |
| `ValidacionesPanelTest`, `CatalogCrudAndBusinessLogicTest` | Textos de validación de los formularios |
| `WebpImageServiceChallengerTest`, `PanelImagenesTest` | Se amplían con las pruebas de la sección 3 |
| `SitioBarTest`, `SitioCafeteriaTest`, `SitioEditorialOscuroTest`, `SitioPastelTest`, `SitioPizzeriaTest` | Afirman `array_keys(Catalogos::plantillasSitio())` y nombres de plantilla. **No deben romperse**: no se renombran claves ni nombres; las descripciones nuevas se agregan en una estructura aparte |
| `tests/Js` | Sin cambios |

Como regla: ningún paso de entrega cambia a la vez rutas y textos de varias pantallas; así las pruebas que fallen son pocas y atribuibles.

---

## 8. Riesgos, tamaño y orden de entrega

### 8.1 Riesgos

| Riesgo | Mitigación |
|---|---|
| Dos paneles con datos duplicados: editar en uno y no ver el cambio en el otro | Decidir D1 antes de construir; ambos escriben en las mismas columnas, así que no hay riesgo de datos, solo de confusión |
| Cambios de rutas rompen marcadores y pruebas | Mantener las rutas actuales hasta el último paso y redirigir las antiguas |
| Fotos giradas / error 500 en producción mientras se rediseña | Paso 1: endurecimiento de imágenes, antes de cualquier cambio visual |
| Vista previa expone una plantilla no publicada | Solo propietario/gerente autenticado, parámetro firmado, sin caché, no indexable |
| Roles: dar más acceso a gerente o editor sin querer | D4 antes de tocar políticas; pruebas de matriz |
| Textos en español incompletos | Auditoría de textos por pantalla en cada paso |
| El árbol de trabajo tiene cambios sin confirmar (plantilla H, `Catalogos`, pruebas, `vite.config.js`) | No se tocan; la Fase 2 parte de una rama limpia o de tu confirmación |
| Usuarios con 2FA y sesión larga ven menú viejo | Sin impacto; no hay almacenamiento de navegación |

### 8.2 Tamaño estimado

Tallas relativas (S = medio día, M = 1 a 2 días, L = 3 a 5 días de trabajo con pruebas):

| Paso | Contenido | Talla |
|---|---|---|
| 1 | Endurecimiento y unificación de imágenes (sección 3) con pruebas | M |
| 2 | Textos y ayudas: eliminar "carta", jerga y límites contradictorios (sin mover pantallas) | S |
| 3 | Inicio nuevo: "Qué falta configurar" con avance | M |
| 4 | Navegación nueva: grupos, orden, barra inferior (rutas actuales intactas) | S |
| 5 | Información: Datos, Contacto y ubicación, Horario semanal | L |
| 6 | Pedidos y reservas: reservas y WhatsApp en Filament | M |
| 7 | Apariencia: Diseño del sitio, Logotipo y fotos, galería | L |
| 8 | Vista previa de plantilla (depende de D2) | M |
| 9 | QR y mesas: "Crear QR de mesas" | S |
| 10 | Unificar `/panel` y Filament (depende de D1) | L |

Total aproximado: 3 a 4 semanas de trabajo, entregable en 10 piezas mergeables.

### 8.3 Orden recomendado

Cada paso es independiente, deja el CI en verde y se puede fusionar solo:

1. **Imágenes seguras y mensajes correctos** (paso 1 + parte del 2). Es el único que corrige fallos que hoy afectan al dueño (fotos giradas, error 500, "5 MB" falso) y no cambia la navegación.
2. Textos y ayudas (2).
3. Inicio nuevo (3).
4. Navegación nueva (4).
5. Pedidos y reservas (6): saca reservas de `/panel`, muy pedido.
6. Información (5).
7. Apariencia (7) y vista previa (8).
8. QR y mesas (9).
9. Unificación con `/panel` (10), al final y solo si se aprueba.

**Recomiendo empezar por el paso 1.** Si prefieres empezar por algo visible, el segundo mejor es el paso 3 (Inicio).

---

## 9. DECISIONES QUE NECESITO DE MÍ

Cada una con opciones y mi recomendación.

**D1. ¿Qué hacemos con los dos paneles (`/admin` y `/panel`)?**
- A. Dejar `/admin` como panel único de gestión y dejar `/panel` solo para el trabajo en vivo (Reservas, Pedidos y tablero del día); quitar de `/panel` los ajustes, menú y equipo. **Recomendado.**
- B. Mantener ambos completos y solo alinear textos.
- C. Llevar todo a `/panel` y retirar Filament para el dueño (reescritura grande).

**D2. Vista previa de la plantilla**
- A. Vista previa en vivo con el contenido real (requiere un cambio pequeño en el controlador del sitio público: parámetro autorizado y no guardado). **Recomendado.**
- B. Imágenes de muestra estáticas por plantilla (no toca el sitio público).
- C. Sin vista previa; solo mejores descripciones.

**D3. Unificar las subidas de imagen en un solo servicio con recorte y copias responsivas**
- A. Sí, todas las subidas usan el mismo camino. **Recomendado.**
- B. Mantener los dos caminos y solo arreglar los fallos.
Esto puede cambiar el aspecto de portadas ya subidas desde Filament si se vuelven a guardar; las existentes no se reprocesan.

**D4. Roles (necesito aprobación expresa; toca permisos)**
Hoy el gerente puede cambiar casi todo en `/panel` pero no en Filament. Opciones:
- A. El gerente puede lo mismo en ambos paneles que el propietario, salvo dar el rol de propietario (hoy solo lo asigna un propietario o el superadmin). **Recomendado.**
- B. Reducir al gerente en `/panel` para igualarlo con Filament.
- C. No tocar roles por ahora y mostrar cada pantalla solo a quien hoy la ve.

**D5. Límite de tamaño de las fotos**
- A. Aceptar hasta 10 MB y reducir en el servidor. **Recomendado**, porque las fotos de celular suelen pesar de 3 a 8 MB.
- B. Mantener 2 MB y corregir el texto.
- C. 5 MB (lo que dice hoy la ayuda).

**D6. Extras opcionales**
- Mapa por enlace de Google Maps en lugar de latitud/longitud: sí / no. Recomiendo sí.
- Botón "Enviarme un mensaje de prueba" para los pedidos por WhatsApp: sí / no. Recomiendo dejarlo para después.
- Crear QR de mesas por cantidad: sí. Recomiendo sí.

**D7. Orden de platillos y categorías**
- A. Arrastrar y soltar (el campo numérico queda oculto). **Recomendado.**
- B. Mantener el campo numérico.

**D8. Dónde va "Mostrar platillos agotados"**
- A. Dentro de Mi menú, junto a la lista de platillos (afecta lo que se ve en el menú). **Recomendado.**
- B. En Pedidos y reservas, como hoy en Pedidos por WhatsApp.

**D9. Textos "carta" en el sitio público**
Hay textos públicos con "carta" (por ejemplo "Carta no disponible", "carta impresa", "La carta digital que tus comensales recordarán.") cubiertos por pruebas. Dijiste que no se toque el sitio público.
- A. No tocarlos ahora. **Recomendado.**
- B. Cambiarlos a "Menú" en un paso aparte, actualizando las pruebas.

**D10. Archivo de traducciones**
- A. Crear `lang/es/panel.php` solo para los textos nuevos del panel. **Recomendado.**
- B. Dejar los textos dentro de las clases, como hoy.

**D11. Quién edita la dirección corta (slug)**
- A. Solo la plataforma, como hoy. **Recomendado.**
- B. El propietario, con advertencia de que cambia el enlace y puede romper enlaces compartidos.

**D12. Qué rama usar**
Hay cambios sin confirmar (plantilla H, `Catalogos`, pruebas de plantillas, `vite.config.js`, fuentes). Para la Fase 2 necesito saber si parto de esta rama tal como está o espero a que los confirmes.

---

## 10. Lo que no pude confirmar sin ejecutar

- Las rutas exactas de Filament (`/admin/{restaurante}/...`) por `route:list` (el comando no devolvió resultados en mi intento); los nombres de ruta de los recursos salen del código.
- Quién puede ver Invitaciones e Historial de cambios (`InvitacionPolicy`, `HistorialCambioPolicy`).
- Cuáles de las plantillas e–h ignoran por completo el "estilo visual" y el "color principal".
- Cabeceras HTTP de `/storage` y el comportamiento del borrado suave de platillos respecto a sus fotos.
- Si el servidor tiene activada la extensión `exif` (para leer la orientación).
