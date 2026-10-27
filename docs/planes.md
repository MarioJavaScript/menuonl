# Planes de venta de Menubly: análisis y propuesta (Fase 1)

Estado: **solo análisis. No hay código ni migraciones escritas.** Espera tu aprobación. No se hizo `git add`, `commit` ni `push`, y no se tocó `frontend/`, el diseño de las plantillas ni `/panel`.

Todo sale de leer el código en la rama `planes-restaurante`. No ejecuté las pruebas. Lo que no pude confirmar leyendo está marcado como "por confirmar".

Un plan **no es un rol**. Los roles siguen igual (propietario y superadmin hacia afuera). El plan es lo que compró el restaurante, y funciona como un **techo**: lo que una persona puede hacer es lo que su rol permite **y** lo que el plan del restaurante incluye. El superadmin no tiene ese techo para editar.

Nombres que se verían en pantalla (ver D1): **Completo**, **Sitio y menú**, **Solo menú**. Valores guardados: `completo`, `sitio_menu`, `solo_menu`.

---

## 0. Hallazgos que cambian el diseño

1. **Hay dos puertas al mismo dato.** El panel `/admin/{slug}` (Filament) y el panel de servicio `/panel` (Blade + JavaScript con API `/api/v1/panel/...`). Si solo se recorta Filament, un propietario de "Sitio y menú" podría seguir cambiando plantilla, fotos y platillos desde `/panel → Ajustes` y `/panel → Menú`. **Cada regla del plan debe aplicarse en las dos puertas**, y la API de `/panel` debe responder 403 aunque el botón esté oculto.
2. **El superadmin ya puede editar cualquier restaurante** (lo confirmé, ver 2.7). No hace falta construir nada para eso; solo hay que no ponerle el techo del plan.
3. **`sitio_plantilla` no es solo del sitio.** `MenuPublicoController` usa la misma columna para elegir el diseño del **menú** (`menu.plantillas.e` a `h`, o `menu.publico` para a–d). En "Solo menú" la plantilla sigue mandando en cómo se ve el menú. Por eso **no se borra ni se ignora** al cambiar de plan.
4. **Hoy no existe una pantalla de métricas.** No hay contador de escaneos ni de visitas. Lo más cercano es el resumen de Inicio (turnos de hoy, pendientes, reservas de hoy y por confirmar). Ver D5.
5. **Existen más roles de los que dices exponer:** `propietario`, `gerente`, `editor`, `recepcion` (el rol `cocina` se retiró junto con los pedidos manuales; más el superadmin, que es una marca aparte). El plan debe recortar a todos los que no son superadmin. Ver D6.
6. **Los códigos QR no dependen del plan.** `/q/{codigo}` siempre lleva a `/{slug}/menu` (nunca al sitio). Un QR impreso sigue funcionando en los tres planes.
7. **`ProgresoDeConfiguracion` y el widget de Inicio** muestran pendientes con enlaces a pantallas que un plan puede cerrar. Hay que filtrarlos con la misma regla central (ver 2.4).

---

## 1. Inventario y a qué plan pertenece

Leyenda: ✔ puede · ✖ no puede · ◐ parcial (se explica) · ⚠ depende de una decisión. "Prop." = propietario (y demás roles, recortados por el techo). El superadmin puede todo en los tres planes.

### 1.1 Panel Filament `/admin/{slug}`

| Pantalla o acción | Clase | Completo (prop.) | Sitio y menú (prop.) | Solo menú (prop.) | Nota |
|---|---|---|---|---|---|
| Entrar al panel (inicio de sesión, perfil, dos factores) | `Login`, `canAccessPanel` | ✔ | ✔ | ✖ | En Solo menú no hay entrada. Ver D10 |
| Inicio (resumen y pendientes) | `Inicio` + `ResumenRestauranteWidget` | ✔ | ◐ solo pendientes de lo que sí puede | ✖ | El widget debe ocultar pendientes sin salida |
| Platillos | `ProductoResource` | ✔ | ✖ | ✖ | Capacidad `editar_menu` |
| Categorías | `CategoriaResource` | ✔ | ✖ | ✖ | `editar_menu` |
| Recomendados | `Pages\Recomendados` | ✔ | ✖ | ✖ | Usa la política de Platillos |
| Horario | `HorarioResource` | ✔ | ✔ | ✖ | `editar_horario` |
| Contactos | `ContactoResource` | ✔ todos los tipos | ◐ solo teléfono y WhatsApp | ✖ | Filtrar por `tipo` en lista, formulario y política |
| Códigos QR (lista, crear, editar) | `CodigoQrResource` | ✔ | ⚠ D4 | ✖ | Recomiendo ✖ (como acordado) |
| Descarga de QR | `QrDownloadController` | ✔ | ⚠ D4 | ✖ | Sigue la política de QR |
| Apariencia (plantilla, estilo, color, logotipo, portada) | `Pages\Apariencia` | ✔ | ✖ | ✖ | `editar_diseno` |
| Fotos de tu restaurante (galería) | `Pages\FotosDelRestaurante` | ✔ | ✖ | ✖ | `editar_diseno`. Ver sección 6 |
| Datos del restaurante (nombre, frase, descripción, mapa, moneda, idiomas) | `EditRestauranteProfile` | ✔ | ✖ | ✖ | `editar_datos` |
| Ajustes de reservas y pedidos: número de WhatsApp de pedidos | `Pages\AjustesDeReservasYPedidos` | ✔ | ✔ solo el número | ✖ | Es uno de los tres teléfonos. Ver D3 |
| Ajustes de reservas y pedidos: activar pedidos | idem | ✔ | ⚠ D3 | ✖ | |
| Ajustes de reservas y pedidos: reservas (activar, cupo, duración) | idem | ✔ | ⚠ D2 | ✖ | En Solo menú esta sección no existe ni para el superadmin |
| Equipo (cuentas) | `UserResource` | ✔ | ✖ | ✖ | `equipo` |
| Invitaciones | `InvitacionResource` | ✔ | ✖ | ✖ | `equipo` |
| Historial de cambios | `HistorialCambioResource` | ✔ | ✖ | ✖ | La política hoy solo deja ver al propietario y al superadmin |
| Enlace "Panel de servicio" (`/panel`) | `AdminPanelProvider` | ✔ | ⚠ D2 | ✖ | |
| "Ver mi sitio" (encabezado, Inicio, Datos) | hook `ver-menu-publico` | ✔ | ✔ | ✖ | En Solo menú solo "Ver mi menú" |
| "Ver mi menú" | idem | ✔ | ✔ | ✔ superadmin | |
| Barra inferior del celular | hook `barra-inferior` | ✔ | ◐ | ✖ | Ya filtra con `canViewAny`; las que no filtran (Inicio, Más) por revisar |
| Plataforma: Restaurantes y Cuentas | `RestauranteResource`, `UserResource` | ✖ | ✖ | ✖ | Solo superadmin. Aquí vivirá el plan |

### 1.2 Panel de servicio `/panel` (API `/api/v1/panel/{slug}/...`)

> **Transitorio.** `/panel` y su API describen el estado actual, no el definitivo: `docs/integracion-dashboard.md`, sección 17, los pasa a páginas de Filament y deja `/panel` solo como puerta de redirección. Las reglas de plan y rol de este documento (`PermisosPanel`, D2, D7, D10) siguen valiendo y se aplican igual en las páginas nuevas.

El acceso a cada módulo vive en `PermisosPanel::modulosDe()` y se aplica con `ModuloPanel`. Es el lugar natural para el techo del plan.

| Módulo | Qué hace | Completo | Sitio y menú | Solo menú | Nota |
|---|---|---|---|---|---|
| Inicio | Resumen del día | ✔ | ⚠ D2 | ✖ | |
| Reservas | Ver, crear, cambiar estado, borrar | ✔ | ⚠ D2 (recomiendo ✔) | ✖ | Sin esto el propietario no ve las reservas que le llegan |
| Menú | Platillos y fotos de platillos | ✔ | ✖ | ✖ | Segunda puerta de `editar_menu` |
| ~~Pedidos (tablero)~~ | **Retirado** (ya no existe) | — | — | — | Ver D7 |
| Ajustes | Nombre, plantilla, portada, galería, horario, reservas, mapa, equipo | ✔ | ✖ | ✖ | Segunda puerta de casi todo. Teléfonos y horario se editan en Filament |
| Equipo | Invitar, revocar, cambiar rol | ✔ | ✖ | ✖ | |
| Lista de restaurantes del usuario | `GET /api/v1/panel` | ✔ | ✔ | ✖ | Ya oculta restaurantes sin módulos |

### 1.3 Función pública

| Función | Ruta o lugar | Completo | Sitio y menú | Solo menú |
|---|---|---|---|---|
| Sitio con plantilla (a–h) | `GET /{slug}` | ✔ | ✔ | **redirige** al menú (sin error) |
| Menú público | `GET /{slug}/menu` | ✔ | ✔ | ✔ |
| Pedido por WhatsApp (en menú y en sitio E–H) | `pedidos_activos` + `whatsapp_pedidos` | ✔ | ✔ | ✔ |
| Formulario de reservas del sitio | componente `reservar` de cada plantilla | ✔ | ✔ | no existe (no hay sitio) |
| Disponibilidad y reserva (API pública) | `/api/v1/sitio/{slug}/disponibilidad` y `/reservas` | ✔ | ✔ | **cerrada**: responde como "reservas apagadas" |
| Galería de fotos | sección del sitio (plantillas a–c) | ✔ | ✔ | no se ve (solo está en el sitio) |
| Código QR y NFC | `GET /q/{codigo}` | ✔ | ✔ | ✔ (siempre va al menú) |
| Dirección anterior del menú | `GET /m/{slug}` (301) | ✔ | ✔ | ✔ |
| Dirección anterior del sitio | `GET /s/{slug}` (301 a `/{slug}`) | ✔ | ✔ | queda 301 a `/{slug}` y de ahí 302 al menú |
| API pública del menú | `/api/v1/menu/{slug}` | ✔ | ✔ | ✔ |
| Portada de Menubly (`/`) | `PortadaController` | no depende del plan | | |

### 1.4 Tabla campo por campo: quién lo cambia

Es la tabla de referencia para las pruebas. "Sup." = superadmin. Los tres teléfonos son: **llamada** (Contacto tipo `telefono`), **WhatsApp de contacto** (Contacto tipo `whatsapp`) y **WhatsApp de pedidos** (columna `whatsapp_pedidos`).

| Campo o dato | Dónde se edita hoy | Completo | Sitio y menú | Solo menú |
|---|---|---|---|---|
| Plantilla (`sitio_plantilla`) | Apariencia, `/panel → Ajustes` | Prop. y Sup. | Sup. | Sup. |
| Estilo visual (`tema`) | Apariencia | Prop. y Sup. | Sup. | Sup. |
| Color principal (`color_acento`) | Apariencia | Prop. y Sup. | Sup. | Sup. |
| Logotipo, portada | Apariencia, Plataforma, `/panel` | Prop. y Sup. | Sup. | Sup. |
| Galería de fotos | Fotos, `/panel → Ajustes` | Prop. y Sup. | Sup. | Sup. (no se muestra) |
| Platillos, categorías, variantes, etiquetas, fotos de platillos, recomendados | Menú, `/panel → Menú` | Prop. y Sup. | Sup. | Sup. |
| Nombre, frase, descripción | Datos, `/panel → Ajustes` | Prop. y Sup. | Sup. | Sup. |
| Mapa (latitud y longitud) | Datos, `/panel → Ajustes` | Prop. y Sup. | Sup. | Sup. |
| Moneda, idioma principal, idiomas extra, zona horaria | Datos (zona y moneda también en Plataforma) | Prop. y Sup. | Sup. | Sup. |
| Dirección, Instagram, Facebook, TikTok, sitio web, correo (Contactos) | Contactos | Prop. y Sup. | Sup. | Sup. |
| **Teléfono de llamada** | Contactos | Prop. y Sup. | **Prop. y Sup.** | Sup. |
| **WhatsApp de contacto** | Contactos | Prop. y Sup. | **Prop. y Sup.** | Sup. |
| **WhatsApp de pedidos** (número) | Ajustes de reservas y pedidos | Prop. y Sup. | **Prop. y Sup.** | Sup. |
| Activar o apagar pedidos por WhatsApp (`pedidos_activos`) | Ajustes de reservas y pedidos | Prop. y Sup. | ⚠ D3 | Sup. |
| **Horario** | Horario, `/panel → Ajustes` | Prop. y Sup. | **Prop. y Sup.** | Sup. |
| Mostrar platillos agotados (`mostrar_agotados`) | Pedidos o Datos (por confirmar la pantalla) | Prop. y Sup. | Sup. | Sup. |
| Reservas: activar, cupo, duración | Ajustes de reservas y pedidos, `/panel → Ajustes` | Prop. y Sup. | ⚠ D2 | no aplica |
| Atender reservas recibidas | Reservas (`/admin/{slug}/reservas`; antes `/panel → Reservas`) | Prop. y Sup. | ⚠ D2 | no aplica |
| Códigos QR y mesas | Códigos QR | Prop. y Sup. | ⚠ D4 | Sup. |
| Equipo e invitaciones | Equipo, Invitaciones, `/panel → Ajustes` | Prop. y Sup. | Sup. | Sup. |
| Nombre de la dirección corta (`slug`), estado, **plan** | Plataforma | Sup. | Sup. | Sup. |

Métricas: ver D5.

---

## 2. Propuesta técnica

### 2.1 Dónde se guarda el plan (requiere migración: **te la propongo y espero tu visto bueno**)

Una columna en `restaurantes`:

```php
$table->string('plan', 20)->default('completo')->after('estado');
```

- Valores: `completo` (8 caracteres), `sitio_menu` (10) y `solo_menu` (9). Caben en `string(20)` sin riesgo en MariaDB estricto.
- Valor por defecto `completo`, `NOT NULL`. Con ese valor por defecto la base de datos llena sola todas las filas existentes con `completo`. **No hay que reescribir datos** y los restaurantes actuales no cambian de comportamiento.
- `down()` quita la columna.
- Sin índice (son pocos restaurantes y no se busca por plan).
- En el modelo: `'plan' => 'completo'` en `$attributes` (igual que las demás columnas con valor por defecto) y un `cast` a un enum.
- **No va en `$fillable`.** Se cambia solo con una instrucción explícita (`forceFill`) desde Plataforma. Así ningún formulario ni API puede cambiarlo por accidente con datos enviados por el usuario.
- Enum: `app/Enums/PlanRestaurante.php` (la carpeta `app/Enums` ya existe, tiene `TipoCodigoAcceso`). No se crea carpeta nueva.

### 2.2 La regla central de capacidades

Dos preguntas distintas que hoy se mezclarían en condiciones sueltas:

- **Pública** (¿existe esto para el visitante?): depende solo del plan. No hay usuario.
- **De panel** (¿puede esta persona cambiarlo?): depende del plan **y** del usuario. El superadmin siempre puede.

Piezas (nombres propuestos, ajustables):

1. `App\Enums\Capacidad`: lista cerrada de capacidades.
2. `App\Enums\PlanRestaurante::incluye(Capacidad): bool`: la **única tabla** plan → capacidades (abajo).
3. `Restaurante::incluye(Capacidad): bool`: atajo (`$restaurante->plan->incluye(...)`).
4. `Restaurante::permiteA(User, Capacidad): bool`: `true` si el usuario es superadmin; si no, `incluye(...)`.
5. Un envoltorio por caso de uso, para que nadie repita la condición: `Restaurante::tieneSitio()` y `Restaurante::reservasDisponibles()` (= `reservas_activas && incluye(Reservas)`).

Capacidades y plan que las incluye:

| Capacidad | Completo | Sitio y menú | Solo menú |
|---|---|---|---|
| `sitio` (existe `/{slug}` como página) | ✔ | ✔ | ✖ |
| `reservas` (formulario público y su API) | ✔ | ✔ | ✖ |
| `panel` (el propietario entra) | ✔ | ✔ | ✖ |
| `editar_diseno` (plantilla, estilo, color, logo, portada, galería, recomendados) | ✔ | ✖ | ✖ |
| `editar_menu` (platillos, categorías) | ✔ | ✖ | ✖ |
| `editar_datos` (nombre, frase, descripción, mapa, idiomas, otros contactos) | ✔ | ✖ | ✖ |
| `editar_telefonos` (los tres teléfonos) | ✔ | ✔ | ✖ |
| `editar_horario` | ✔ | ✔ | ✖ |
| `qr` (ver, crear, descargar) | ✔ | D4 | ✖ |
| `equipo` (cuentas e invitaciones) | ✔ | ✖ | ✖ |
| `servicio` (módulos de `/panel`) | todos | D2 | ✖ |
| `metricas` | ✔ | ✖ | ✖ |

"Pedidos por WhatsApp" no es una capacidad de plan: funciona en los tres (columna `pedidos_activos` y número). Solo cambia quién lo configura.

### 2.3 Cómo se recorta el panel Filament

Un solo punto de control en lugar de tocar cada recurso:

- **Políticas.** `MatrizPolicy::permite()` ya recibe el restaurante y el nivel. Se agrega una constante opcional por política (`CAPACIDAD`). Si el nivel **no** es superadmin y el plan del restaurante no incluye esa capacidad, devuelve `false`. Todas las políticas hijas (`Producto`, `Categoria`, `Horario`, `Contacto`, `CodigoQr`, `GaleriaImagen`, `Reserva`, `Pedido`, `Invitacion`) heredan el recorte con una línea cada una. Los recursos, la navegación, la barra inferior y las acciones de tabla ya preguntan a la política, así que se ocultan solos. El plan de un restaurante se consulta una vez por petición (memoria por id) para no multiplicar consultas.
- **Contactos por tipo.** `ContactoPolicy` revisa además `tipo`: en Sitio y menú solo `telefono` y `whatsapp`. La lista se filtra por esos tipos y el selector de tipo del formulario solo ofrece esos dos. La política es la que decide (el filtro es comodidad), así que no se puede saltar editando la petición.
- **Páginas que editan columnas del restaurante.** `EditaAjustesDelRestaurante::canAccess()` hoy solo pide `update` sobre el restaurante. Cada página agrega su capacidad: `Apariencia` → `editar_diseno`; `EditRestauranteProfile::canView` → `editar_datos`; `AjustesDeReservasYPedidos` → `editar_telefonos` (la página, antes "Pedidos y reservas" y desde el PR 3 "Ajustes de reservas y pedidos", se ve, pero sus secciones se muestran según D2 y D3). `FotosDelRestaurante` y `Recomendados` ya van por políticas.
- **Página `AjustesDeReservasYPedidos` en Sitio y menú.** Muestra solo el campo del número de WhatsApp de pedidos (y lo demás según D2 y D3). En el servidor, `save()` debe **descartar** los campos que el plan no permite aunque lleguen en la petición. Esta es la defensa real; ocultar el campo es solo apariencia.
- **Acceso y cambio de restaurante.** `User::canAccessTenant()` y `getTenants()` dejan fuera los restaurantes en Solo menú para quien no es superadmin. `canAccessPanel()` es falso si todos sus restaurantes activos son Solo menú. `ContextoRestaurantePanel` usa `canAccessTenant`, así que `/panel` queda cerrado con el mismo cambio. Cada petición vuelve a evaluarlo: una sesión abierta pierde el acceso en cuanto se baja el plan.
- **Enlaces.** `ver-menu-publico` recibe la URL del sitio solo si `tieneSitio()`. `ResumenRestauranteWidget` y `ProgresoDeConfiguracion` omiten pendientes cuyo destino el usuario no puede abrir (hoy ya filtran por política; falta revisar `reservas_sin_horario`, que apunta al horario y debe depender de `reservasDisponibles()`).
- **Dos factores.** `requiereDosFactores()` hoy se exige a cualquier propietario. Un propietario solo de restaurantes en Solo menú no entra, así que no tiene sentido exigírselo. Se refina después (baja prioridad).

### 2.4 Cómo se aplica en `/panel`

- `PermisosPanel::modulosDe()` ya es el filtro único. Se le suma el techo del plan: `modulos del rol ∩ modulos del plan`. Completo: todos. Sitio y menú: vacío o solo `reservas` (D2). Solo menú: vacío.
- Como `ModuloPanel`, la lista de restaurantes del propietario y `panel.js` se apoyan en esa función, **no hace falta tocar `frontend/` ni `resources/js/panel.js`**; el servidor deja de ofrecerle módulos.
- Hay que comprobar que ningún endpoint de `/panel` quede fuera de `ModuloPanel` (hoy todos están dentro; una prueba por ruta lo vigilará).

### 2.5 Cómo se aplica en el sitio público

- `SitioPublicoController::mostrar()`: después de comprobar que el restaurante existe y está activo, si no `tieneSitio()` → `redirect()->route('menu.publico', [...$request->query(), 'slug' => $slug], 302)`. Conserva `?lang=` y `?mesa=`. Respuesta **302 y no 301** (ver 3.1).
- `SitioPublicoDatos`: la clave `reservas_activas` pasa a `reservasDisponibles()`. Con eso las 8 plantillas ocultan el botón y el formulario de reservas sin tocar su diseño.
- `ReservasPublicas::crear()` y `ReservaPublicaController::disponibilidad()`: usan `reservasDisponibles()` en lugar de `reservas_activas`. Reutilizan las respuestas que ya existen para "reservas apagadas" (sin franjas y el mensaje "Por ahora no recibimos reservas en línea."), así que no hay respuestas nuevas ni textos nuevos.
- `/q`, `/m` y `/api/v1/menu` no cambian.

### 2.6 Cómo lo edita el superadmin en Plataforma

- `RestauranteResource` ya es solo del superadmin. Se agrega: **columna** con el nombre del plan (etiqueta de color) y **filtro** por plan en la lista.
- **Crear:** selector de plan, por defecto Completo.
- **Cambiar de plan:** acción "Cambiar plan" en la edición y en la fila de la lista, con ventana de confirmación que dice en español sencillo qué cambia (por ejemplo: "Tus visitantes que abran la página del sitio verán el menú. Se cierran las reservas en línea. El propietario ya no podrá entrar al panel") y, si hay, cuántas reservas por confirmar quedan. Aplica con `forceFill` y deja registro en el historial de cambios.
- Al guardar se invalida la caché del restaurante y del borde (`MenuCacheService` ya dispara `PurgarCacheRestauranteJob`; por confirmar que cubre la dirección del sitio, el trabajo la incluye).
- Textos: nuevo bloque `panel.planes.*` en `lang/es/panel.php` (nombres y explicaciones de cada plan, sin jerga, "menú" nunca "carta").

### 2.7 Cómo edita hoy el superadmin cualquier restaurante (confirmado)

1. **Selector de restaurante del panel.** `User::getTenants()` devuelve **todos** los restaurantes si es superadmin; entra a `/admin/{slug}` de cualquiera.
2. **Nivel `superadmin` en cada política.** Todas las matrices tienen `superadmin` con todas las acciones, y `canAccessTenant` le abre cualquier restaurante. Edita platillos, apariencia, contactos, horario, QR, equipo, etc., igual que un propietario.
3. **Plataforma → Restaurantes.** Solo nombre, dirección corta, moneda, idioma, zona horaria, estado, logo y portada. Ahí es donde se agregará el plan.
4. **`/panel`.** `modulosDe()` le da todos los módulos. Ojo: la lista de restaurantes de `/panel` para el superadmin está limitada a 50 (`limit(50)`); está fuera de este alcance, pero conviene saberlo.

Con planes, el superadmin conserva todo: `permiteA()` y `MatrizPolicy` lo dejan pasar por encima del techo. Una mejora de uso, no obligatoria: mostrar el plan del restaurante en el encabezado cuando el superadmin está editando (D11).

---

## 3. Cambios de plan con rutas, enlaces, QR y plantilla

### 3.1 Qué pasa con rutas y enlaces

| Cambio | Qué le pasa a lo que ya existe |
|---|---|
| Completo → Sitio y menú | Sitio, menú, QR y reservas siguen. El propietario pierde pantallas de diseño, menú, datos y equipo. Nada se borra. |
| Completo / Sitio y menú → Solo menú | `/{slug}` redirige (302) a `/{slug}/menu`. Los QR siguen yendo al menú: **no se rompen**. `/s/{slug}` va a `/{slug}` y de ahí al menú (dos saltos; aceptable). El formulario de reservas deja de existir y su API responde "reservas apagadas". El propietario no puede entrar. |
| Solo menú → Sitio y menú o Completo | El sitio vuelve a mostrarse **con la misma plantilla** que quedó guardada. Las reservas vuelven con los mismos valores guardados (`reservas_activas`, cupo, duración). |

- **Por qué 302 y no 301:** el plan puede subir otra vez. Un 301 se guarda "para siempre" en navegadores y en el borde (Cloudflare) y haría que el sitio no volviera a abrirse. Con 302 y la caché corta que ya usa el sitio (30 segundos) el cambio se nota casi de inmediato. Ver D7.
- **Reservas ya hechas:** no se borran ni se cambian. Quedan guardadas; el superadmin las ve. Si hay reservas por confirmar, la ventana de confirmación avisa.
- **Pedidos por WhatsApp:** siguen igual en los tres planes.
- **Cuentas del equipo:** las filas de membresía y las invitaciones pendientes se conservan. Si el plan vuelve a subir, recuperan lo que tenían. En Solo menú no pueden entrar mientras tanto.

### 3.2 Qué pasa con la plantilla

- Se conserva siempre. En **Solo menú** sigue decidiendo el diseño del menú (a–d con el estilo y color; e–h con su propio diseño). El superadmin la elige igual que antes.
- Las reglas de qué plantillas usan el estilo (`PLANTILLAS_CON_ESTILO`) o muestran galería (`PLANTILLAS_CON_GALERIA`, solo a–c) no cambian. Lo nuevo es que "muestra galería" también depende de que el plan tenga sitio (sección 6).

---

## 4. Pruebas

### 4.1 Pruebas existentes que hay que revisar (no ejecuté nada; esta lista sale de leer qué prueba cada archivo)

Se espera que **no cambien** porque el plan por defecto es `completo`, pero conviene correrlas en el primer paso para confirmarlo:

- Aislamiento y políticas: `PoliticasMatrizTest`, `RoleAndPolicyAuthorizationTest`, `AislamientoPanelTest`, `AislamientoModelosTest`, `PanelTenancyTest`, `RestauranteMultiTenantIsolationTest`, `RestauranteAislamientoTest`.
- Panel Filament: `PanelNavegacionDuenoTest`, `PanelRolesOperativosTest`, `PanelAparienciaTest`, `PanelFotosDelRestauranteTest`, `PanelAjustesDeReservasYPedidosTest`, `PanelInicioTest`, `PanelRecomendadosTest`, `PanelDatosYMapaTest`, `PanelEquipoTest`, `PanelQrMesasTest`, `FilamentAdminAdversarialTest`, `FilamentM4ChallengerTest`.
- `/panel` (API): `PanelAjustesTest`, `PanelApiMenuYResumenTest`, `PanelApiReservasTest`, `PanelImagenesTest`, `PanelMenuOrdenYAgotadosTest`, `Api/`.
- Público: `SitioPublicoTest`, `SitioBarTest`, `SitioCafeteriaTest`, `SitioPastelTest`, `SitioPizzeriaTest`, `SitioEditorialOscuroTest`, `ReservasPublicasTest`, `ReservasBarTest`, `ReservasCafeteriaTest`, `ReservasPastelTest`, `ReservasPizzeriaTest`, `DireccionesLimpiasTest`, `QrRedirectYDescargaTest`, `MenuPublicoTest`, `PerfilPlantillaSitioTest`.
- Base de datos y siembra: `DatabaseMigrationAndSeederTest`, `DatabaseLayerAndSeedersTest`, `DemoRestauranteSeederTest`, `RespaldoMenuonlTest` y los de respaldo (si el respaldo lista columnas, hay que incluir `plan`).

Los que **sí** pueden requerir cambio según lo que se haga:

- Si se agrega un chequeo de columnas o esquema en las pruebas de base de datos o de respaldo, incluir `plan`.
- Pruebas que cuentan elementos de navegación por rol (`PanelNavegacionDuenoTest`): si el widget o la barra cambian para algún plan, se agregan casos, no se alteran los de Completo.

### 4.2 Pruebas nuevas

Reglas de estilo (lecciones del CI): **sin ids fijos** (crear con fábricas y usar `$restaurante->id`), **valores que quepan en su columna** (`plan` ≤ 20), y compatibles con MariaDB estricto y Node 22.

Fábrica de `Restaurante`: dos estados nuevos, `sitioMenu()` y `soloMenu()`.

| Grupo | Qué se prueba |
|---|---|
| Unidad: reglas | `PlanRestaurante::incluye()` para cada capacidad y plan (la tabla de 2.2 completa, una fila por caso). Un valor desconocido en la base se trata como `completo` o falla de forma segura (D12). |
| Migración | El valor por defecto es `completo`; filas existentes quedan en `completo`; no se puede guardar un plan fuera de la lista. |
| Sin sesión | Completo: `/{slug}` 200 con formulario de reservas. Sitio y menú: igual. Solo menú: `/{slug}` 302 al menú con `?lang=` y `?mesa=` conservados; `/{slug}/menu` 200; `/q/{codigo}` 302 al menú; `/s/{slug}` termina en el menú; `POST` de reserva y `GET` de disponibilidad no crean ni ofrecen nada; pedido por WhatsApp sigue funcionando. Restaurante suspendido sigue en 404 (no en redirección). |
| Propietario, Completo | Todo lo que hoy puede (las pruebas actuales ya lo cubren). |
| Propietario, Sitio y menú | Puede: horario, teléfono de llamada, WhatsApp de contacto, número de WhatsApp de pedidos. No puede (pantalla 404 y acción 403): platillos, categorías, recomendados, apariencia, fotos, datos, QR (según D4), equipo, invitaciones; crear un contacto de tipo dirección o Instagram; editar uno existente por petición directa; cambiar `sitio_plantilla`, `color_acento`, `nombre`, `pedidos_activos` o `reservas_*` enviándolos en la petición de la pantalla de pedidos (deben descartarse). Menú de navegación muestra solo lo permitido. |
| Propietario, Sitio y menú, `/panel` | La API de cada módulo no permitido responde 403: `ajustes`, `menu`, `equipo` y fotos. Un recorrido de **todas las rutas** de `/api/v1/panel/{slug}/...` que confirma que ninguna queda sin `ModuloPanel`. |
| Propietario, Solo menú | No entra a `/admin` ni a `/panel`; las API responden 404 (igual que un restaurante ajeno); un propietario con un restaurante Completo y otro Solo menú ve solo el primero. |
| Superadmin | En los tres planes puede abrir todas las pantallas del restaurante y editar sus datos; en Solo menú puede editar plantilla, menú y contactos; la sección de reservas no existe para Solo menú. En Plataforma cambia el plan; el propietario que no es superadmin no puede (política y prueba directa). |
| Cambio de plan | Cambiar el plan guarda, deja huella en el historial de cambios y vacía la caché; no borra reservas ni equipo; subir de nuevo restaura el sitio; un formulario que mande `plan` no lo cambia (no está en `$fillable`). |
| Aislamiento | El plan de un restaurante nunca afecta a otro (dos restaurantes con planes distintos en la misma prueba). |
| Avisos | El aviso de Fotos y las fichas de Apariencia se comportan como se describe en la sección 6. |

---

## 5. Riesgos y orden de entrega

### 5.1 Riesgos

| Riesgo | Cómo se controla |
|---|---|
| **Segunda puerta** (`/panel`) deja saltar el recorte | Se recorta en `PermisosPanel` en el mismo paso que Filament, con prueba que recorre todas las rutas |
| **Ocultar no es proteger:** un campo oculto igual se puede enviar | La política y `save()` deciden en el servidor; la prueba envía campos prohibidos |
| Mucho cambio de políticas a la vez rompe pruebas de aislamiento | Un solo punto (`MatrizPolicy`), y el valor por defecto `completo` deja todo como está |
| Consultas extra por el plan en cada política | Memoria por petición del plan por restaurante |
| `plan` guardado con un valor raro (edición manual en la base) | `cast` a enum con salida segura (D12) |
| Un 301 de `/{slug}` se queda en caches | Usar 302 y caché corta; purgar al cambiar de plan |
| Propietarios de Solo menú ven un error feo al entrar | Mensaje claro en español (D10) |
| El superadmin edita sin ver en qué plan está | Mostrar el plan en el encabezado (D11) |
| El plan queda editable antes de que el recorte esté listo | **El selector de Plataforma se entrega al final** (paso 5) |
| La plantilla "desaparece" en Solo menú y se pierde | No se borra; sigue mandando en el menú |
| `limit(50)` de la lista de restaurantes de `/panel` para el superadmin | Fuera de alcance; se anota |
| Las plantillas e–h incluyen reservas dentro del sitio | Se oculta con `reservasDisponibles()` en `SitioPublicoDatos`, sin tocar su diseño |

### 5.2 Orden de entrega (cada paso se puede unir por separado)

1. **Base, sin efectos.** Migración, enum de plan y de capacidades, atajos en el modelo, estados de fábrica, textos de los planes, pruebas de unidad y de migración. Nadie puede cambiar un plan todavía. Todo sigue como está.
2. **Sitio público.** Redirección de Solo menú, `reservasDisponibles()` en sitio, servicio de reservas y API pública. Pruebas "sin sesión" por plan (usando la fábrica para poner el plan).
3. **`/panel`.** Techo del plan en `PermisosPanel`, bloqueo de acceso en `canAccessTenant` y `getTenants`, prueba de todas las rutas de la API.
4. **Panel Filament.** Constante `CAPACIDAD` en las políticas, `ContactoPolicy` por tipo, páginas de ajustes (`Apariencia`, `Datos`, `AjustesDeReservasYPedidos` con descarte de campos), widget, pendientes, barra inferior y enlaces "Ver mi sitio". Pruebas por plan y por rol.
5. **Plataforma.** Columna y filtro por plan, selector al crear, acción "Cambiar plan" con confirmación, historial y limpieza de caché. **Aquí se enciende el uso real.** Antes de este paso ningún restaurante puede salir de Completo.
6. **Ajustes de uso** (opcionales): plan visible en el encabezado para el superadmin, mensaje de Solo menú al intentar entrar, avisos de las secciones 6, refinar los dos factores.

---

## 6. Interacción con el aviso de "Fotos de tu restaurante" y con las fichas de plantillas

**Aviso de Fotos.** Hoy `FotosDelRestaurante` muestra un aviso cuando la plantilla elegida no enseña la galería (`AparienciaSitio::muestraGaleria()`), diciendo cuál plantilla tiene y con un botón a Apariencia visible solo si `Apariencia::canAccess()`. Con planes:

- **Completo:** sin cambios.
- **Sitio y menú:** el propietario no entra a Fotos, así que no ve el aviso. El superadmin lo sigue viendo y funciona igual.
- **Solo menú:** la galería **nunca** se muestra, porque la galería vive en el sitio. El aviso actual ("tu plantilla no muestra galería, cámbiala") sería engañoso, porque cambiar la plantilla no ayuda. Propongo para el superadmin otro texto: "Este restaurante tiene el plan Solo menú: no tiene página de sitio, así que estas fotos no se muestran." La función `muestraGaleria()` no cambia; se le suma la pregunta del plan en el lugar donde se usa.

**Fichas de plantillas** (`AparienciaSitio::fichas()`, los nombres claros). Solo las ve quien puede editar la apariencia: propietario en Completo y superadmin siempre.

- Los nombres de `Catalogos::plantillasSitio()` **no cambian** (los usan `/panel` y las pruebas); las fichas siguen siendo solo textos de pantalla.
- En Solo menú, las descripciones hablan del "sitio". Propongo un aviso arriba de la selección: "Este plan solo tiene menú: la plantilla cambia cómo se ve el menú, no hay página de sitio." El estilo visual (a–d) y el color se siguen aplicando al menú.
- `AparienciaSitio::estadoDelColor()` usa una clave de texto llamada `solo_menu` para plantillas a–c ("el color se ve en el menú"). Es una coincidencia de nombre con el plan; no hay conflicto técnico, pero en el código conviene no confundirlos. No se renombra ahora.

---

## 7. DECISIONES QUE NECESITO DE MÍ

Cada una trae mi recomendación. Con tu visto bueno empiezo por el paso 1.

| # | Decisión | Opciones | Recomendación |
|---|---|---|---|
| **D1** | Nombres en pantalla | (a) Completo / Sitio y menú / Solo menú; (b) otros | **(a)**, con los valores guardados `completo`, `sitio_menu`, `solo_menu` tal como los diste |
| **D2** | Reservas en **Sitio y menú**: ¿el propietario las ve y las atiende? ¿configura activarlas, cupo y duración? | (a) Ver y atender (módulo Reservas de `/panel`), configurarlas solo el superadmin; (b) ni ver ni configurar (solo superadmin); (c) todo | **(a).** Sin ver las reservas que llegan, la función no sirve. Es la única excepción al "panel mínimo" y se limita a ese módulo. Con (a), Ajustes, Menú y Pedidos de `/panel` siguen cerrados |
| **D3** | WhatsApp de pedidos en Sitio y menú | (a) Solo cambia el número; (b) número y activar o apagar pedidos | **(a).** Es un teléfono, como pediste. Apagar o encender pedidos lo hace el superadmin |
| **D4** | Códigos QR en Sitio y menú | (a) Solo el superadmin; (b) el propietario ve y descarga, sin crear ni editar | **(a)**, como está acordado. (b) es fácil de agregar después si lo piden |
| **D5** | Métricas: hoy no existe ninguna pantalla ni contador | (a) No crear nada ahora; solo dejar la capacidad `metricas` definida (Completo y superadmin) y aplicarla cuando exista la pantalla; (b) incluir ahora las cifras del resumen de Inicio como "métricas" | **(a).** Construir métricas es otro proyecto. Dime si lo quieres como siguiente |
| **D6** | Roles operativos (gerente, editor, recepción; `cocina` se retiró) | (a) El plan recorta a todos; en Solo menú nadie (salvo superadmin) entra; (b) el plan solo recorta al propietario | **(a).** El plan es techo para todos; sigue sin exponerse ningún rol nuevo |
| **D7** | Tablero de Pedidos de `/panel` (pedidos registrados a mano). **Retirado:** no hay pedidos de mesa; módulo `pedidos` y rol `cocina` eliminados | (a) Solo Completo; (b) también Sitio y menú | **(a)** (histórico). "Pedidos por WhatsApp" es el envío desde el menú y vale en los tres planes; el tablero es otra cosa |
| **D8** | Redirección de Solo menú | (a) 302 con caché corta; (b) 301 | **(a).** Con 301 el sitio podría no volver a abrir tras subir de plan |
| **D9** | Cómo se cambia el plan | (a) Acción "Cambiar plan" con ventana de confirmación que explica qué cambia; (b) campo en el formulario de edición | **(a).** Evita cambios por error y queda en el historial. Al crear un restaurante se elige con selector (por defecto Completo) |
| **D10** | Propietario de Solo menú que intenta entrar | (a) Mensaje: "Tu plan no incluye el panel. Escríbenos si quieres cambiar algo" con un contacto | **(a), ajustada.** El contacto no va escrito en el código: se lee de `config('menuonl.soporte_whatsapp')`, que viene de `SOPORTE_WHATSAPP` en el `.env` (solo dígitos, con lada, formato wa.me). Si está vacía, el mensaje no muestra botón y solo dice que contacte a su proveedor. Si tiene valor, muestra un botón que abre `wa.me` con el texto "Hola, quiero cambiar algo de mi menú". Lo resuelve `App\Services\ContactoSoporte` |
| **D11** | Mostrar el plan al superadmin que edita un restaurante | (a) Etiqueta en el encabezado; (b) no | **(a)**, en el paso 6 |
| **D12** | Valor de plan inválido en la base de datos | (a) Tratar como `completo`; (b) tratar como el plan más restrictivo (`solo_menu`) | **(b).** Si algo raro entra, fallar hacia lo más cerrado es más seguro; la migración y el enum evitan que ocurra |
| **D13** | Orden de entrega | (a) Selector de Plataforma al final (paso 5); (b) primero el selector | **(a).** Así ningún restaurante sale de Completo antes de que el recorte esté listo |
| **D14** | Propiedad de la migración | Autorizas la migración de la sección 2.1 tal como está (`plan`, `string(20)`, `completo`, sin reescribir datos) | **Sí.** No cambia datos existentes |

### Qué no cambio sin tu aprobación

- La migración y cualquier cambio de datos existentes.
- Las políticas y permisos (cambian lo que ve cada usuario).
- Cualquier cambio visible para propietarios actuales. Con el valor por defecto `completo`, **ningún propietario actual ve diferencia** hasta que el superadmin cambie un plan.
