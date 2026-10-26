# Integración del dashboard nuevo (Fase 1: análisis, sin código)

Estado: análisis de la Fase 1 con las **decisiones aprobadas** (sección 9). El PR 0 (marca y modo claro por defecto) ya está hecho en código; el resto sigue siendo análisis.

Antes: **solo análisis y documentación.** No se cambió código de la aplicación, no se escribió en la carpeta del prototipo, no se hizo `git add`, `commit` ni `push`.

## 0. Cómo se hizo y qué NO se verificó

- **La ruta del prototipo es otra.** El encargo decía `C:\Users\thexl\Desktop\dashboardUXUI`; esa carpeta no existe. Leí `C:\Users\thexl\Desktop\dashboard-ux` (README, `docs/direccion-de-diseno.md`, `docs/integracion.md`, `docs/investigacion.md` y la lista de pantallas coinciden con lo descrito). Si hay otra copia, avísame.
- Leí: README, los tres documentos de diseño, `src/lib/layout.mjs` (tabla `CAPS`), `src/css/filament-theme.css`, `src/css/tokens.css`, los nombres de `Alpine.data(...)` de `src/js/app.js`, los JSON de `data/` y **3 capturas** (Inicio de La Fonda del Sol, Pedidos y reservas, Resumen de plataforma). **No abrí las otras ~109 capturas ni el HTML de cada una de las 128 pantallas**: el inventario de la sección 1 sale de la lista de pantallas del README, de los datos y de esas tres capturas. Cada fila marcada "por revisar" necesita mirar la pantalla antes de su PR.
- Del proyecto real leí: `docs/planes.md`, la estructura de `docs/panel-restaurante.md`, `AdminPanelProvider`, `PlanRestaurante`, `Capacidad`, `PoliticaDeContenido`, `CabecerasDeSeguridad`, `resources/css/filament/admin/theme.css`, las migraciones, `lang/es/panel.php` (claves de primer nivel) y los nombres de las pruebas. No leí cada Resource ni cada página de Filament línea por línea.
- **No ejecuté** nada: ni la aplicación, ni el prototipo, ni las pruebas. Versiones confirmadas en `composer.lock`: Filament 5.10.2, Livewire 4.4.8.
- **No verifiqué** que los selectores `fi-*` del borrador `filament-theme.css` existan en Filament 5 (el propio prototipo lo declara sin verificar; yo tampoco lo comprobé en `vendor/`). Tampoco lectores de pantalla ni navegadores distintos de Chromium.
- Dos capturas muestran la barra lateral cortada a ~900 px de alto y la barra "Guardar cambios" encima del formulario (Pedidos y reservas). Parece un artefacto de la captura (elementos pegajosos), pero no lo comprobé en el navegador.

## 1. Hallazgos que cambian el plan

1. **El prototipo se llama Menubly; el proyecto decía Menuonl.** **Resuelto:** el nombre real es Menubly y "menuonl" era provisional. El nombre visible vive en un solo valor, `config('app.name')` (`APP_NAME`, por defecto `Menubly`), y las pruebas lo leen de ahí. Los identificadores internos conservan `menuonl` (decisión 1). El logotipo actual (la M) se queda hasta que llegue el arte de Menubly.
2. **Hay dos paneles.** El prototipo supone uno. El real tiene Filament en `/admin/{slug}` y el panel de servicio `/panel` (Blade + JavaScript + API `/api/v1/panel/...`, con su propio CSS `resources/css/panel.css` y `panel.js`). Hoy **las reservas se atienden en `/panel`**, no en Filament; la pantalla de Filament "Pedidos y reservas" solo configura (WhatsApp, activar reservas, cupo, duración). El prototipo junta configuración y lista de reservas en una sola pantalla.
3. **Alpine: el prototipo usa `@alpinejs/csp`; Filament no.** Filament y Livewire traen su propio Alpine estándar, por eso `/admin` lleva `'unsafe-eval'` en la CSP (`PoliticaDeContenido::admin`, `docs/despliegue.md` 6.1). El JavaScript del prototipo (`Alpine.data(...)`) sirve tal cual en `/panel`, `/invitaciones` y páginas Blade fuera de Filament; **dentro de Filament** los componentes interactivos deben hacerse con Livewire/Alpine de Filament o con `x-data` estándar, no cargando un segundo Alpine.
4. **El plan y sus capacidades ya existen** (`PlanRestaurante::incluye()`, `Capacidad`, políticas, `PanelRecortePorPlanTest`, `PlataformaPlanTest`…). El prototipo trae su propia tabla `CAPS` (duplicada) que **no debe portarse**.
5. **No existen métricas.** Hay `Capacidad::Metricas` pero ninguna pantalla ni contador: la tabla `codigos_qr` no tiene columna de escaneos (columnas: restaurante_id, nombre, mesa, codigo, activo, tipo) y `QrRedirectController` solo redirige.
6. **Los tokens del tema actual son compartidos con el menú público** (`--mo-*`, comentario de `theme.css`: "Mismos tokens que public/css/menuonl.css") y hay una prueba que lee `public/css/menuonl.css`. El rediseño del panel no debe tocar esos tokens.

## 2. Inventario pantalla por pantalla

Leyenda: **Existe** = hay equivalente real; **Cambia** = existe, el diseño nuevo la rehace; **Nuevo** = no existe; **Sin backend** = el diseño muestra datos que hoy no se guardan.

### 2.1 Plataforma (solo superadmin)

| Pantalla del prototipo | Hoy en el proyecto | Estado | Notas |
|---|---|---|---|
| Resumen (avisos, cifras, restaurantes por plan, escaneos por día, últimos cambios) | Nada. Un superadmin entra a la lista de Restaurantes | **Nuevo** + **Sin backend** | Cifras de restaurantes y por plan sí salen de `restaurantes`; escaneos QR/NFC no existen; "avisos" (reservas sin confirmar >24 h, respaldo fallido, invitación expirada, restaurante inactivo) no se calculan hoy |
| Restaurantes (lista) | `RestauranteResource` + `RestaurantesTable` | **Cambia** | Mismos datos; cambia presentación (tarjetas en celular, glifo de plan, filtros) |
| Ficha de restaurante con selector de plan | `EditRestaurante`, `CambiarPlanAction`, `filament/partials/cambio-de-plan.blade.php`, `PlanRestaurante::cambiosHacia()` | **Cambia** | Ya existe "qué se gana / qué se pierde". Falta: aviso de reservas por confirmar, "Deshacer 10 s" (nuevo) |
| Usuarios e invitaciones | `UserResource` (solo lista) + `InvitacionResource` | **Cambia** | Dos recursos hoy separados; el diseño los junta. Columna "último acceso": por revisar si existe en `users` |
| Métricas (global y por restaurante) | Nada | **Nuevo** + **Sin backend** | Ver sección 6 |
| Respaldos | Solo comandos (`respaldar`, `verificar`, `restaurar`), horario y aviso de antigüedad | **Nuevo** + **Sin backend de UI** | Hay respaldos reales en almacenamiento; falta leerlos y listarlos. Descargar o restaurar desde la web es riesgoso: recomiendo solo lista de lectura |
| Historial de cambios (global) | `HistorialCambioResource` (por restaurante) | **Cambia** + **Nuevo** | Vista global entre restaurantes no existe |
| Ajustes de plataforma (WhatsApp y correo de soporte, dominio, zona) | `config('menuonl.soporte_whatsapp')` desde `.env` (`ContactoSoporte`) | **Nuevo** | Hoy no hay tabla de ajustes; editarlos en pantalla exige una tabla nueva o dejarlos en `.env` |

### 2.2 Panel del restaurante

| Pantalla del prototipo | Hoy | Estado | Notas |
|---|---|---|---|
| Inicio (saludo con Slashi, avisos, lista "qué falta", accesos rápidos) | `Inicio` + `ResumenRestauranteWidget` + `ProgresoDeConfiguracion`; pruebas en `PanelInicioTest` | **Cambia** | La lógica (7 pasos, avisos por rol y plan) ya existe. Es la pantalla más portable |
| Platillos / editar platillo | `ProductoResource` | **Cambia** | "Marcar agotado" ya existe; "Más opciones" plegado y barra fija inferior son de presentación |
| Categorías, Recomendados | `CategoriaResource`, `Pages\Recomendados` | **Cambia** | |
| Plantilla, Estilo y colores, Logotipo y portada | `Pages\Apariencia` (una sola página) | **Cambia** | El prototipo las separa en tres pantallas; hoy es una. Decidir si se divide |
| Fotos de tu restaurante | `Pages\FotosDelRestaurante` | **Cambia** | Arrastrar para ordenar: por revisar contra la subida actual (`ImagenRestauranteUpload`) |
| Datos del restaurante | `EditRestauranteProfile` | **Cambia** | |
| Horario, Contactos | `HorarioResource`, `ContactoResource` | **Cambia** | Contactos en "Sitio y menú": solo teléfono y WhatsApp (ya implementado) |
| Pedidos y reservas | `Pages\PedidosYReservas` (config) + reservas en `/panel` | **Cambia** + **Nuevo en Filament** | Calendario y lista del día con Confirmar/Cancelar: hoy en `/panel`. Decisión 2 de la sección 9: se quedan en `/panel` |
| QR y mesas | `CodigoQrResource`, `QrDownloadController` | **Cambia** | Escaneos por mesa: **Sin backend** |
| Métricas del restaurante | Nada | **Nuevo** + **Sin backend** | Solo plan Completo y superadmin (`Capacidad::Metricas` ya existe) |
| Equipo e invitaciones | `UserResource` / `InvitacionResource` | **Cambia** | El prototipo solo modela propietario; el real tiene 5 roles (propietario, gerente, editor, recepción, cocina) |
| Historial de cambios | `HistorialCambioResource` | **Cambia** | Marca "por superadmin": por revisar si el historial ya guarda al actor |
| Banner "Estás viendo como superadmin" | Nada | **Nuevo** | Hoy el superadmin entra al panel de un restaurante sin banner. Se hace con un `renderHook` (BODY_START) |
| Barra inferior en celular (Inicio, Mi menú, Pedidos, Apariencia, Más) | `filament/hooks/barra-inferior.blade.php` (`mo-barra-inferior`) | **Cambia** | Existe; hoy: Inicio, Platillos, Categorías, Horario, Más (la prueba lo fija) |

### 2.3 Sistema

| Pantalla | Hoy | Estado |
|---|---|---|
| Inicio de sesión | `Filament\Pages\Auth\Login` | **Cambia** (tema) |
| Tu plan no incluye el panel | `PlanSinPanelController` + `plan/sin-panel.blade.php` | **Cambia** (ya cumple D10; solo se rediseña) |
| Errores 404/500 | Vistas por defecto de Laravel | **Nuevo** (con arte de Slashi pendiente) |
| Componentes, tokens, estados | `diseno/componentes.blade.php` (`PaginasDeDisenoTest`) | **Por revisar**: existe una página de diseño propia; decidir si se reemplaza |

## 3. Cómo integrarlo: tres opciones

| | A. Tema de Filament + componentes propios | B. Vistas Blade propias (sin Filament) | C. Mezcla |
|---|---|---|---|
| Qué es | `->viteTheme()` con tokens `--mb-*`, `->colors()`, Blade `x-mb.*` dentro de páginas personalizadas de Filament | Layout propio; se abandona Filament para el panel | Tema (A) para recursos y formularios; páginas Blade propias dentro de Filament para Resumen, Métricas, Inicio, selector de plan, banner |
| Costo | Medio. Un CSS, hooks y componentes pequeños | **Muy alto**: rehacer formularios, tablas, subida de fotos, 2FA, tenencia por restaurante, notificaciones, validaciones y la mayor parte de ~1 190 líneas de `UxUiAccesibilidadTest` y decenas de pruebas de Filament | Medio-alto, por etapas |
| Riesgo | Selectores `fi-*` frágiles en cada actualización de Filament; el borrador está sin verificar | Perder seguridad ya hecha (2FA, aislamiento, CSP con nonce, Livewire); semanas de trabajo | Dos estilos conviviendo hasta terminar |
| Cabe el diseño | ~80 % (tablas→tarjetas en celular, glifo de plan y banner cuestan más) | 100 % | ~95 % |

**Recomendación: C, empezando por A.** Primero tema y marca (bajo riesgo, reversible), luego pantallas nuevas como páginas de Filament con vista Blade propia, y los componentes `x-mb.*` solo donde Filament no tiene equivalente (glifo de plan, checklist "comanda", línea de lectura, banner). No reemplazar tablas ni formularios de Filament. Para `/panel` (Blade + JS) aprobado: se queda con las reservas (decisión 2 de la sección 9) y solo recibe los tokens de color; mover las reservas a Filament y retirar `/panel` sería un proyecto propio (hay API pública, `PanelApi*Test` y pruebas JS).

Consecuencia técnica: los tokens se renombran con prefijo `mb-` y se **añaden** al tema del admin; no se tocan `--mo-*` ni `public/css/menuonl.css`.

## 4. Planes y recorte por capacidades en el diseño nuevo

Regla: **una sola fuente.** El prototipo duplica `CAPS`; en el proyecto real el menú, los botones y los avisos salen de `PlanRestaurante::incluye(Capacidad)` y de las políticas/`canViewAny` de cada Resource o página (como ya hace el panel). Nada de `@if($plan === 'completo')` en las vistas.

Comparación del prototipo con la realidad (`PlanRestaurante::incluye`):

| Concepto | Prototipo | Real | Acción |
|---|---|---|---|
| Completo | menú, diseño, datos, horario, teléfonos, pedidos, QR, métricas, equipo, historial | todo | Coincide |
| Sitio y menú | horario, teléfonos, pedidos | Sitio, Reservas, Panel, EditarTelefonos, EditarHorario, Servicio. QR, equipo y pedidos (activar) los maneja solo el superadmin (D3, D4) | Coincide en lo importante; el real además da **reservas**. Verificar que "pedidos" en el prototipo = solo el **número** de WhatsApp (es lo que dice `planes.md`) |
| Solo menú | sin panel | sin panel (`canAccessPanel` falso para el propietario) | Coincide |
| Superadmin | ve todo, lo no incluido marcado "Solo tú" | sin techo de plan para editar | Coincide; el marcador "Solo tú" es nuevo |
| Roles | solo propietario | propietario, gerente, editor, recepción, cocina, con techo del plan | **El diseño no cubre roles.** Hay que definir qué ve cada uno en Inicio y en el menú (hoy ya filtrado en `PanelInicioTest`) |

Reglas para el nuevo diseño:
1. Lo no incluido en el plan se muestra **apagado con una frase** ("Incluido en el plan Completo"), pero solo para elementos que el propietario *podría* tener; nunca para lo que es exclusivo del superadmin (QR y equipo en Sitio y menú), salvo que lo decidas (pendiente, sección 9).
2. Ocultar un enlace **no es seguridad**: las dos puertas (Filament y `/api/v1/panel`) siguen respondiendo 403. Las pruebas actuales de recorte (`PanelRecortePorPlanTest`, `PanelPlanAccesoTest`, `PlataformaPlanTest`, `PlanSinPanelTest`) deben seguir en verde sin cambios de lógica.
3. Métricas: `Capacidad::Metricas` ya existe; hoy `PlanRestaurante::cambiosHacia()` no la menciona porque no hay texto de lang. Al construir la pantalla se añade el texto "gana/pierde" correspondiente.
4. El selector de plan reutiliza `cambiosHacia()` y `CambiarPlanAction`; lo nuevo es el aviso de reservas por confirmar y "Deshacer".
5. El banner de superadmin lee el tenant actual y el usuario; no cambia permisos.

## 5. Impacto en pruebas, idioma, CSP, cabeceras y accesibilidad

**Pruebas que dependen del HTML o de textos (revisar antes de cada PR):**
- `UxUiAccesibilidadTest` (≈170 aserciones de texto, muchas sobre el menú público que no cambia): las del panel — navegación "Inicio" (no "Escritorio"), `class="mo-barra-inferior"`, `aria-label="Accesos rápidos"`, etiquetas "Platillos/Categorías/Horario/Más", marca (`test_panel_tiene_marca_propia_favicon…`), detalle de historial con componentes Filament, tablas con estados vacíos en español, "Deshacer" al eliminar.
- `PanelInicioTest` (66 aserciones): textos de la checklist, "no jerga, carta ni emojis" (`test_los_textos_no_usan_jerga_carta_ni_emojis`: **los textos nuevos del prototipo deben pasar esa regla**), barra de progreso accesible, enlaces por rol.
- `PanelNavegacionDuenoTest`, `PanelTextosDelDuenoTest`, `PlanSinPanelTest` (22 aserciones sobre el texto de la pantalla "sin panel"), `PlataformaPlanTest`.
- Pruebas que leen archivos (`PaginasDeDisenoTest`, la de `public/css/menuonl.css`): no se rompen si no se tocan esos archivos.
- Riesgo de fondo: casi todas usan textos en español; cambiar redacción para "palabras del negocio" rompe aserciones. Regla: **cambiar texto y prueba en el mismo PR**, y mover los textos nuevos a `lang/es/panel.php` en vez de duplicarlos.
- Las pruebas de Filament no compilan assets (`temaCompilado()` evita fallar sin build), así que no verán el CSS nuevo: una regresión visual no la detecta ninguna prueba actual. Hará falta revisión manual o capturas.

**Idioma (`lang/es/panel.php`, 423 líneas, ya organizado por secciones: grupos, inicio, planes, plan_sin_panel…):** el prototipo trae textos en español de México dentro de funciones JS. Se reutilizan las claves que ya existen (la propia `integracion.md` del prototipo lo pide) y solo se agregan claves nuevas bajo secciones nuevas (`plataforma`, `metricas`, `respaldos`). Unificar "menú" (nunca "carta") y tuteo.

**CSP y cabeceras (recién implementadas):**
- `/admin`: `script-src 'self' 'nonce-…' 'unsafe-eval'`, `style-src 'self' 'unsafe-inline' https://fonts.bunny.net`, `font-src 'self' data: https://fonts.bunny.net`. Las fuentes Space Grotesk y Figtree se sirven con `@fontsource-variable` por Vite (mismo origen): **entran en `'self'` sin cambiar la CSP**. No cargar nada de Google Fonts. Hay que quitar la dependencia de `fonts.bunny.net` solo si se reemplaza la fuente de Filament (opcional).
- Cualquier `<script>` en línea que agreguen los componentes nuevos dentro de Filament necesita el nonce (`Vite::cspNonce()`); mejor, ninguno: la lógica en archivos.
- Íconos: **Heroicons** (decisión 5 de la sección 9), los de Filament con `DisableBladeIconComponents`; los íconos Lucide del prototipo se sustituyen por su equivalente Heroicon. Los SVG en línea no necesitan CSP (son HTML).
- Imágenes de Slashi/logotipos: SVG propios en `public/brand/` (`img-src 'self'`).
- `/panel`/`/invitaciones`: CSP estricta (`script-src 'self'`, sin eval) que sí es compatible con el Alpine CSP del prototipo.
- Cabeceras: `Permissions-Policy` ya no nombra ninguna función de la aplicación (no se usa cámara, etc.). **Si el diseño nuevo añadiera selector de archivos con cámara o "copiar al portapapeles" revisar esa política.** `X-Frame-Options: SAMEORIGIN`: no usar iframes de otro origen. Las pruebas `CabecerasDeSeguridadTest` recorren cada grupo de rutas y deben seguir en verde; las pantallas nuevas deben pasar por el middleware global (lo hacen por defecto).
- Métricas: la privacidad ("sin IP ni cookies") debe seguir siendo cierta (ver sección 6).

**Accesibilidad:** el prototipo declara contrastes WCAG AA comprobados con su script, objetivos de 44 px, foco de 3 px, `prefers-reduced-motion`. Lo que hay que validar al portar: (a) el tema actual ya tiene foco de 2 px y bordes de 3:1; el nuevo foco de 3 px sustituye, no se suma; (b) el color primario de Filament pasa de Amber a `#1B62B5` (texto blanco sobre ese botón: 6.07 según el prototipo, **no recalculé** los contrastes); (c) modo oscuro: **se conserva el mecanismo actual** (clase `.dark` de Filament y `data-apariencia` en lo público, ambos con la clave `filament:theme:mode`) y el modo **claro es el predeterminado** (PR 0); no se porta el `data-theme` del prototipo; (d) lectores de pantalla no verificados por el prototipo ni por mí.

## 6. Datos del prototipo que no son reales y qué construir

Todo el prototipo es ficticio (`data/*.json`, generado por `scripts/generar-datos.mjs`).

| Dato del prototipo | Real hoy | Qué construir |
|---|---|---|
| Escaneos QR y NFC, por día, por restaurante y por mesa (**alcance aprobado**) | No se guardan | Contar en `QrRedirectController` (cada visita a `/q/{codigo}` pasa por el servidor). Tabla agregada por (código, día) con contador, sin IP ni cookies; limpieza programada. La columna `tipo` (`qr`/`nfc`) ya existe |
| "Visitas al sitio" y "al menú" | No se guardan | **Descartado** mientras Cloudflare cachee esas páginas: el servidor no vería la mayoría de las visitas. Se quita del diseño (cifras, líneas y tarjetas de visitas) y no se muestra ninguna cifra que no se pueda medir bien. Se reconsidera si cambia el caché |
| Reservas por restaurante/día en métricas | Existen en `reservas` | Consulta agregada; sin tabla nueva |
| Alertas de plataforma (reservas >24 h sin confirmar, respaldo fallido, invitación expirada, restaurante sin publicar) | No se calculan | Servicio de avisos con consultas sobre `reservas`, `invitaciones`, respaldos y `productos`; guardar "descartada" solo si lo quieres |
| Lista de respaldos (fecha, tipo, tamaño, estado) y programación | Comandos, horario y aviso de antigüedad | Servicio de solo lectura sobre el almacenamiento de respaldos; "Falló el respaldo" necesita registrar el resultado |
| Último acceso de usuarios | Por confirmar en `users` | Columna `ultimo_acceso` si no existe |
| Historial global y "por superadmin" | Historial por restaurante | Consulta global; comprobar que se guarda el actor |
| Ajustes de plataforma | `.env` | **Solo lectura** (decisión 6): se muestran los valores de configuración, sin tabla nueva ni formulario |
| Número `525555550100`, `soporte@menubly.example`, `menubly.example`, restaurantes de ejemplo (La Fonda del Sol…) | Soporte desde `.env` (`SOPORTE_WHATSAPP`) | No portar; leer de configuración. Los nombres de ejemplo pueden servir para el seeder demo |
| "Deshacer" cambio de plan (10 s) | No existe | Guardar el plan anterior en sesión o historial y acción inversa; escribir en historial |
| Estado "suspendido" con confirmación escribiendo el nombre | Existe `estado` (activo/suspendido) | Solo UI |

## 7. Lo que falta del lado del diseño

- **Slashi para error, éxito y sin conexión:** el prototipo deja espacios reservados (960 × 490 px de arte; el documento de diseño habla de 240 × 122 px, **inconsistencia entre sus dos documentos: confirmar tamaño**). Solo existe el SVG oficial de Slashi con gorro de chef. Falta el arte de esas tres poses y, si se quiere, de 404/500.
- **Marca:** el paquete es Menubly. Si se queda Menuonl, faltan logotipo, símbolo y favicon; hoy el panel usa `filament/logo.blade.php` y `public/favicon.svg`.
- **Íconos:** hay que elegir el Heroicon equivalente de cada ícono Lucide del prototipo; el glifo de plan (tres barras) y Slashi son propios.
- **Pantallas no diseñadas (por revisar):** la gestión de reservas en Filament si se mueve de `/panel`, roles distintos del propietario, plantillas A–H de la selección (solo "Plantilla" a nivel general), estados de carga y error de red en cada pantalla, impresión de QR, emails de invitación (`emails/invitacion.blade.php`).
- **Tabla/lista en celular:** el diseño usa tarjetas; Filament las soporta con columnas responsivas pero requiere trabajo por tabla.

## 8. Orden de entrega en PR pequeños

Cada PR se prueba con la suite correspondiente y, tras los PR 1–2, con revisión manual. Todo PR: `vendor/bin/pint --dirty`, `npm run build`, prueba manual en modo claro/oscuro y a 390 px. Cambios respecto a la primera versión: se añade el **PR 0** (hecho), el PR 2 pasa a ser solo el layout (la marca ya está), `/panel` se queda y recibe solo los tokens, las métricas se reducen a lo medible y Respaldos/Ajustes son de solo lectura.

| # | PR | Contenido | Probar a mano | Qué puede romperse |
|---|---|---|---|---|
| 0 | **Marca y modo claro (hecho)** | Nombre visible en `config('app.name')` (Menubly) en panel, pestañas, pie de menú y sitios, invitaciones, correos, QR imprimible, portada; sin preferencia guardada todo sale en claro; Automático/Claro/Oscuro se conservan | Ver sección 10 | Producción con `APP_NAME=MENUONL` viejo; páginas que dependían de `prefers-color-scheme` |
| 1 | **Tokens y tema (hecho, sección 11)** | Tokens `--mb-*` en el tema del admin (sin tocar `--mo-*`), fuentes por Vite, color primario, foco, 44 px. Sin mover pantallas | Login, Inicio, una tabla, un formulario, subida de foto; claro/oscuro; 390 px | Selectores `fi-*` que no existen; contraste del botón; modo oscuro compartido con el menú público; fuentes bloqueadas por CSP |
| 2 | **Layout (hecho, sección 12)** | Barra lateral y barra inferior (el logotipo sigue siendo la M hasta que llegue el arte) | Navegación en celular; enlaces "Ver mi sitio/menú" | `UxUiAccesibilidadTest` (barra inferior, "Inicio"); `PanelNavegacionDuenoTest` |
| 3 | **Componentes `x-mb.*` (hecho, sección 14)** | Glifo de plan, aviso, vacío, hoja, banner. Se muestran en la página Componentes del panel (solo local); `/diseno` no se toca | La página de componentes | `PaginasDeDisenoTest` |
| 4 | Banner de superadmin | `renderHook` + "Volver a la plataforma" | Entrar a un restaurante como superadmin y como propietario (no debe verse) | Barra pegajosa y su altura sobre la barra superior; toasts encima del banner |
| 5 | Inicio del restaurante | Rediseño de `Inicio` y widget; checklist como comanda; Slashi de chef solo con el arte oficial disponible | Cada rol y cada plan; restaurante vacío | `PanelInicioTest` completo (textos y jerga); filtro por rol |
| 6 | Resto del panel del restaurante (en tandas) | Platillos/categorías, apariencia, datos/horario/contactos, QR; una tanda por grupo | Editar y guardar cada formulario; deshacer; agotado | Pruebas de tablas y formularios de `UxUiAccesibilidadTest`; carga de imágenes |
| 7 | Plataforma: restaurantes y ficha de plan | Lista, ficha, selector con "qué cambia" y "Deshacer" | Cambiar plan en ambos sentidos; reservas pendientes; suspender | `PlataformaPlanTest`, `PlanRestauranteMigracionTest` |
| 8 | Usuarios e invitaciones, historial global | Pantallas de plataforma | Invitar, reenviar, revocar; filtros | `InvitacionesTest`, `EnvioInvitacionEnColaTest`, historial |
| 9 | Respaldos y ajustes, **solo lectura** | Lista de respaldos (fecha, tipo, tamaño, estado) y valores de ajustes de plataforma; sin descargar, restaurar ni editar | Con respaldos reales en un entorno de prueba; sin respaldos | Lectura del almacenamiento; que no se filtre la contraseña de respaldos ni otros secretos de configuración |
| 10 | **Métricas: backend (hecho, sección 13)** | Contador de escaneos QR/NFC **por mesa** en `/q/*`, tabla agregada por (código, día) sin IP ni cookies. Las reservas se cuentan desde `reservas` | Escanear un QR y ver el contador; carga concurrente | `QrRedirectYDescargaTest`, `QrScanBurstConcurrencyTest`, `QrNfcVolumenYN1Test`; rendimiento de la redirección |
| 11 | Métricas: pantallas y resumen de plataforma | Resumen, métricas global/por restaurante con escaneos y reservas; **sin visitas** | Plan Completo vs otros; restaurante sin datos | Recorte por plan (`Capacidad::Metricas`); texto de privacidad cierto |
| 12 | `/panel` y pantallas de sistema | `/panel` se queda (reservas) y solo recibe tokens de color; 404/500, "sin panel", login | Flujo de invitación completo; plan "Solo menú"; atender una reserva | `PlanSinPanelTest`, `PlanSinPanelDosFactoresTest`, `PanelApi*Test`, CSP de `/panel` |

El PR 10 se adelantó (no depende del diseño) para ir acumulando datos mientras se hace lo visual.

## 9. Decisiones aprobadas

1. **Nombre:** la plataforma se llama **Menubly**; "menuonl" era un nombre genérico provisional. El nombre visible sale de un único valor (`config('app.name')`). Se conservan los identificadores internos: nombre del repositorio, espacios de nombres, `config/menuonl.php`, comandos `menuonl:*`, claves de `localStorage` y cookies (`menuonl_cart_*`), claves de caché, nombres de base de datos, variables de entorno existentes y el dominio configurado (`CLOUDFLARE_DOMAIN`, que decide otra persona). El logotipo actual (la M) se queda por ahora.
2. **Reservas:** se quedan en `/panel` por ahora. `/panel` solo recibe los tokens de color; mover las reservas a Filament queda fuera de este plan.
3. **Integración:** mezcla de tema de Filament y páginas propias (opción C): tema y marca primero; páginas Blade propias dentro de Filament para Resumen, Métricas, Inicio, selector de plan y banner; no se reemplazan tablas ni formularios de Filament.
4. **Métricas:** solo las que se pueden medir con honestidad: **escaneos de QR y NFC por mesa** y **reservas**. Sin visitas al sitio ni al menú mientras Cloudflare las cachee.
5. **Íconos:** Heroicons.
6. **Ajustes de plataforma y respaldos:** se muestran en **solo lectura** (sin tabla de ajustes, sin descargar ni restaurar desde la web).
7. **Modo oscuro:** se conserva el mecanismo actual (compartido con el menú público, clave `filament:theme:mode`), pero **el modo claro es el predeterminado**.

**Pendientes de decisión** (no estaban en este lote): lo exclusivo del superadmin en "Sitio y menú" (QR y equipo): ¿apagado con explicación u oculto?; qué ve cada rol distinto del propietario en el diseño nuevo; tamaño real del arte reservado de Slashi; confirmar que `dashboard-ux` es la carpeta del prototipo; arte de Menubly (logotipo, símbolo, favicon) para sustituir la M.

## 10. PR 0: qué cambia a la vista y qué probar a mano

Cambia a la vista:
- Donde decía "MENUONL" o "Menuonl" ahora dice **Menubly**: encabezado y pestaña del panel, portada, pie "Hecho con…" del menú, "Sitio creado con…" de los sitios con plantilla propia, pantalla de menú no disponible, invitaciones, ficha QR imprimible, página de componentes, descripciones de planes y mensaje del comando de respaldo. El pie de los correos usa el mismo valor.
- Sin preferencia guardada todo se ve en **claro** (antes seguía al sistema operativo): `/admin` y su inicio de sesión, menú y sitio públicos, invitaciones, "tu plan no incluye el panel". `/panel` ya era siempre claro y no cambió.
- El selector marca **Claro** por defecto (antes Automático). Quien eligió Automático, Claro u Oscuro conserva su elección.

A mano, en un navegador real (con el sistema operativo en modo oscuro):
1. Ventana nueva o sin la clave `filament:theme:mode` en `localStorage`: `/admin/login`, el menú público, una página de invitación y "sin panel" se ven en claro.
2. Elegir Oscuro en el menú público, recargar y abrir `/admin`: ambos oscuros (clave compartida). Elegir Automático: sigue al sistema. Elegir Claro: claro.
3. En el menú público, la fila de Apariencia muestra Claro marcado sin preferencia, y la elección persiste al recargar.
4. En el menú de usuario de `/admin` cambiar a Oscuro e ir al menú público: oscuro.
5. Con un carrito guardado de antes, abrir el menú y comprobar que sigue ahí (las claves `menuonl_cart_*` no cambiaron).
6. Revisar la pestaña del navegador en `/`, `/admin` y una invitación; abrir el correo de una invitación de prueba y mirar el pie.
7. En producción: cambiar `APP_NAME` en el `.env` y limpiar la caché de configuración; si no, seguirá saliendo el nombre viejo.

## 11. PR 1 "Tokens y tema": qué se hizo

- **Tokens:** `--mb-*` (superficies, texto, acción, foco, bordes de control, tipografía, medidas y sombras) en `:root` y `:root.dark` de `resources/css/filament/admin/theme.css`. Los `--mo-*` no se tocaron (los comparte el menú público y la barra inferior los sigue usando).
- **Paleta de Filament:** `App\Services\PaletaDelPanel` (primary azul Menubly con 600 = `#1B62B5`, y gray azulado). Es la única fuente que leen el `AdminPanelProvider` y `TemaDelAdminTest`. Filament convierte los hex a OKLCH con 3 decimales; se comprobó que el redondeo vuelve al mismo hex de 8 bits.
- **Botón primario:** claro con hover en el tono 700 (texto 50 sobre 700: 7.50:1); oscuro en azul bebé con texto marino (8.79:1, hover 10.31:1), con variables de Filament sobrescritas sin capa para ganar a sus utilidades.
- **Foco:** un solo contorno de 3 px (`--mb-foco`) en enlaces, botones y elementos con `tabindex`; el anillo de Filament en `.fi-btn` y `.fi-icon-btn` se anula al enfocar para que no se sumen. En oscuro, los anillos de campos, interruptores, casillas y radios usan `--mb-foco` (el tono 500 de Filament daba 2.72:1).
- **Tipografía:** Figtree en todo el panel (`--font-family`), Space Grotesk solo en `.fi-header-heading`, `.fi-section-header-heading` y `.fi-modal-heading`. Los paquetes `@fontsource-variable/*` se importan desde el tema y Vite emite los `.woff2` en `/build/assets` (mismo origen; no cambió la CSP). `->font('Figtree Variable', provider: LocalFontProvider::class)` evita que Filament precargue Inter, que el tema no usa; el enlace al CSS local de Inter (un archivo de `@font-face` sin descargar fuentes si no se usan) sigue porque lo registra Filament como asset.
- **Objetivos táctiles:** 44 px (`--mb-objetivo`) en botones y botones de ícono, como antes.
- **No se tocó:** layout, barra lateral, textos, `lang/es/panel.php`, la CSP, el menú público ni `/panel`.

### Pendientes de accesibilidad (no resueltos en el PR 1)

- **Íconos `gray-400` en modo claro:** `#8AA6C1` sobre blanco da **2.53:1**, por debajo del 3:1 que pide WCAG 1.4.11 para íconos que transmiten información. Filament ya los pintaba así (su gris por defecto daba un valor similar). Decidir en el PR 2 o 6 si se oscurecen a `gray-500` o se acompañan de texto.
- **Texto de ayuda en claro:** `#587390` da **4.64:1** sobre el lienzo `#F3F9FF` (y 4.92:1 sobre blanco). Cumple 4.5:1 pero con **poco margen**; cualquier tono más claro o un fondo más oscuro lo rompe. `TemaDelAdminTest` lo vigila.
- **Hover del botón primario en claro** pasó del tono 500 al 700 por pedido del diseño; el tono 500 se conserva (`#1F6CBF`) para anillos, interruptores y otros usos de Filament.
- **Barra inferior móvil:** pasó a los tokens `--mb-*` en el PR 2 (sección 12).

## 12. PR 2 "Layout": qué se hizo

- **Menú lateral y barra superior (solo CSS):** en `resources/css/filament/admin/theme.css`, sobre las clases `fi-*` verificadas en `vendor/filament/filament` (`fi-sidebar-item-btn`, `fi-sidebar-item.fi-active`, `fi-sidebar-group-label`, `fi-topbar`). Ítems de 44 px, ítem activo con fondo `--mb-superficie-2` e indicador de 3 px a la izquierda (sombra interna, no contorno), íconos y etiquetas de grupo en `--mb-tinta-2` (los íconos `gray-400` del menú ya no se usan). Filament sigue dando el enlace "Saltar al contenido", `aria-current` en el ítem activo, el cajón del celular con su botón (`aria-controls`/`aria-expanded`) y Escape. El menú no es colapsable en escritorio (el prototipo tampoco lo es).
- **Barra inferior (`mo-barra-inferior`, clase conservada):** `App\Services\BarraInferiorDelPanel` recorre una lista por prioridad (Inicio, Platillos, Pedidos y reservas, Apariencia, Categorías, Horario, Contactos y "Hoy") y se queda con los primeros 4 que la persona puede abrir, validando cada uno con el `canAccess`/`canViewAny` de su pantalla. "Hoy" (`/panel`) solo entra para quien no ve la página de Pedidos y reservas. "Más" abre el menú lateral de Filament. La vista `filament/hooks/barra-inferior.blade.php` ya no compara planes. Nombre de la navegación: "Principal" (antes "Accesos rápidos", que repetía el del widget de Inicio). Cuando la etiqueta visible es corta ("Pedidos", "Hoy"), el nombre completo va en `aria-label`. Textos en `lang/es/panel.php`, sección `barra`.
- **Qué lleva la barra:** propietario y superadmin con plan Completo: Inicio, Platillos, Pedidos, Apariencia. Propietario con Sitio y menú: Inicio, Pedidos, Horario, Contactos. Editor: Inicio, Platillos, Categorías, Horario. Gerente: Inicio, Platillos, Hoy. Recepción y cocina: Inicio, Hoy. La barra solo ofrece destinos que el menú lateral también ofrece a esa persona.
- **Acciones pegajosas de formulario** (`EditaAjustesDelRestaurante`, `->sticky()`): en el celular suben a `bottom: calc(var(--mb-barra-inferior) + 0.5rem)`, sin capa porque la utilidad de Filament vive en `@layer utilities`. El contenido recibe relleno inferior y `scroll-padding-bottom` con el mismo token (`--mb-barra-inferior` = 3.5 rem más el área segura).
- **"Ver mi sitio" y "Ver mi menú":** en el celular la barra superior solo lleva "Ver mi menú" y los dos botones aparecen también al pie del menú lateral (hook `SIDEBAR_FOOTER`, solo bajo `lg`). En escritorio no cambió nada. Sin textos nuevos.
- **Foco y movimiento:** sigue habiendo una sola regla de contorno (la del PR 1); el menú y la barra no agregan contornos ni anillos. `prefers-reduced-motion: reduce` apaga las transiciones del cajón, del menú y de la barra.
- **CSP, meta viewport, JavaScript:** sin cambios. Cero JavaScript nuevo; "Más" usa el `$store.sidebar` de Filament.
- **No se tocó:** agrupación y textos de la navegación, ítems apagados por plan (decisión: fuera de este PR), punto rojo de pendientes (otro PR), banner de superadmin (PR 4), el menú público ni `/panel`.

Contrastes (calculados desde los tokens, vigilados en `TemaDelAdminTest`):

| Par | Claro | Oscuro |
|---|---|---|
| Ítem activo (texto) sobre su fondo `--mb-superficie-2` | 7.22:1 | 6.25:1 |
| Barra inferior, acceso activo sobre la superficie | 8.24:1 | 7.11:1 |
| Indicador del ítem activo (componente, mínimo 3:1) | 5.31:1 | 6.25:1 |
| Texto del menú (`--mb-tinta-2`) sobre el fondo activo | 5.70:1 | 6.55:1 |
| Etiqueta de grupo sobre el lienzo | 6.14:1 | 9.21:1 |

### Pendientes anotados en el PR 2

- **`viewport-fit=cover` no se agrega** (decisión: no tocar el `<meta viewport>` de Filament). Sin él, `env(safe-area-inset-bottom)` suele valer 0 en iOS; en Safari normal el navegador ya deja libre la zona inferior, pero en una app instalada en la pantalla de inicio la barra podría quedar pegada al indicador de inicio. No se pudo medir en un dispositivo.
- **Contraste del ítem activo en oscuro:** la propuesta de la Fase 1 (fondo al 16 % de acción) daba 5.03:1, con poco margen. Se sustituyó por `--mb-superficie-2`, que da 6.25:1. Sigue siendo el valor más bajo del menú en oscuro: cualquier cambio de `--mb-superficie-2` o de `--mb-accion` en oscuro debe revisarlo (la prueba exige 4.5:1).
- **Íconos `gray-400` fuera del menú** (tablas, formularios): siguen en 2.53:1 sobre blanco en claro (PR 1). El menú lateral ya no los usa.
- Dos navegaciones en el celular (hamburguesa de la barra superior y "Más" de la barra inferior abren el mismo menú). Se mantienen las dos.
- La regresión visual no la detecta ninguna prueba automática.

### Qué probar a mano (390 px y escritorio, claro y oscuro)

1. Cada rol: propietario, superadmin, editor, gerente, recepción y cocina, con plan Completo, y propietario con Sitio y menú. Comparar la barra con la tabla de arriba. Con Solo menú sigue la pantalla "tu plan no incluye el panel".
2. Que "Plataforma" (Restaurantes, Cuentas) no aparezca al propietario ni en el menú ni en la barra.
3. "Más": abre el menú lateral, el foco queda dentro, Escape lo cierra y el foco vuelve al botón. El botón anuncia expandido/contraído.
4. Teclado: Tab recorre "Saltar al contenido", barra superior, menú lateral, contenido y, al final, la barra inferior. El foco nunca queda tapado por la barra y se ve un solo contorno de 3 px.
5. Pantallas con botones fijos abajo (Apariencia, Datos, Pedidos y reservas): el botón de guardar se ve por encima de la barra, con y sin desplazamiento.
6. Final de una lista larga (Platillos): el último elemento no queda debajo de la barra.
7. Barra superior en 390 px: solo "Ver mi menú"; abrir el menú lateral y confirmar "Ver mi sitio" y "Ver mi menú" al pie (Completo y Sitio y menú; con Solo menú no hay sitio).
8. Ítem activo: en la barra inferior y en el menú lateral, la pantalla abierta es la marcada, y solo una.
9. Preferencia de reducir movimiento activada en el sistema: el cajón y los cambios de color no se animan.
10. Lector de pantalla: la barra se anuncia como "Principal"; "Pedidos" y "Hoy" se leen con su nombre completo; el acceso activo se anuncia como página actual.
11. En un iPhone con muesca o barra de inicio: revisar el borde inferior (ver pendiente de `viewport-fit`).

## 13. PR 10 "Métricas: backend": qué se hizo

Solo backend, sin pantallas. Cuenta los escaneos de QR y NFC por mesa en `/q/{codigo}` y los guarda como un agregado diario.

### Qué se guarda y qué no

- Tabla `escaneos_diarios`: `restaurante_id`, `codigo_qr_id`, `fecha` (día local del restaurante), `tipo` (`qr` o `nfc`) y `conteo`. Llave única `(codigo_qr_id, fecha, tipo)`.
- No se guarda IP, user-agent, cookies, sesión ni la hora del escaneo (la tabla ni siquiera tiene `created_at`/`updated_at`).
- Cuenta solo la redirección válida (302). No cuentan los 404, ni `HEAD`, ni cargas anticipadas (`Sec-Purpose`, `Purpose`, `X-Moz`), ni user-agent que coincida con `/\bbot\b|crawler|spider|preview/i` (palabra completa para "bot": un teléfono "CUBOT" sí cuenta; hay prueba).
- Corre para **todos los planes**. El plan solo limita la lectura: `EscaneoDiarioPolicy` (superadmin y propietario, y solo con plan Completo, `Capacidad::Metricas`). El gerente no tiene acceso.
- Las reservas no se copian: se contarán desde `reservas`. No se cuentan visitas a páginas del menú.
- El conteo se hace después de responder (`defer()`), con una sola sentencia atómica (`INSERT ... ON CONFLICT DO UPDATE` en SQLite, `ON DUPLICATE KEY UPDATE` en MariaDB; `conteo = conteo + 1`). Si falla, se registra el error y la redirección sale igual.

### Los conteos son aproximados

- El filtro de user-agent es mínimo y declarado: no detecta a todos los robots (p. ej. bingbot, Slackbot, WhatsApp o facebookexternalhit pueden contar; Googlebot solo se filtra por la palabra `bot` de su URL). Tampoco distingue una persona que escanea dos veces de dos personas.
- El día es el de la zona horaria del restaurante (si es inválida, la de la aplicación). Cambiar la zona o el tipo del código a mitad de día reparte el conteo entre filas.
- Mostrarlos en el PR 11 como "escaneos registrados", nunca como "personas" o "visitantes únicos".

### Texto de privacidad correcto para el PR 11

Las métricas no guardan IP ni cookies: solo cuántas veces se abrió el código de cada mesa en cada día. **No afirmar que el sistema nunca ve la IP**: la aplicación la usa en memoria para el límite de peticiones (`menu-publico`, clave de caché por IP) y el servidor y Cloudflare conservan sus propios registros de acceso. Redacción sugerida: "Contamos cuántas veces se escanea cada código por día. En ese conteo no guardamos tu IP, tu dispositivo ni cookies."

### Retención

400 días: `menuonl:purgar-escaneos` (diario a las 04:30 en la zona del respaldo, sin solaparse) borra lo anterior al corte en lotes de 1000. Configurable con `METRICAS_RETENCION_DIAS`, con mínimo de 31 días (`config/menuonl.php`, `metricas.retencion_dias`).

### Herramienta manual de concurrencia (no es una prueba automática)

`php artisan menuonl:probar-concurrencia-escaneos --procesos=8 --escaneos=50` lanza varios procesos PHP que cuentan el mismo código a la vez contra un SQLite temporal propio (WAL, espera de bloqueo de 10 s) y compara el total con el esperado. No corre en producción y nunca toca la base de la aplicación. Para probar MariaDB: `--conexion=NOMBRE`, donde la conexión debe existir y su base de datos terminar en `_test` o `_prueba`. En desarrollo dio 240 de 240 (6 procesos × 40).

### Qué no se pudo verificar

- MariaDB real (solo SQLite; la sentencia de `ON DUPLICATE KEY UPDATE` se comprobó en la gramática del framework, no contra el motor).
- Que PHP-FPM libera al cliente antes de contar y que Cloudflare no cachea `/q/*`: pendientes anotados en `docs/despliegue.md`, sección 5.
- Revisión manual con tinker: `App\Models\EscaneoDiario::withoutGlobalScopes()->latest('id')->limit(5)->get()` tras abrir un `/q/CODIGO` en el navegador.

## 14. PR 3 "Componentes x-mb.*": qué se hizo

Cinco componentes Blade en `resources/views/components/mb/`, para las pantallas nuevas del panel (Inicio, Resumen, Métricas). No reemplazan los de Filament en formularios y tablas, y no se movió ninguna pantalla existente.

| Componente | Props y ranuras | ARIA |
|---|---|---|
| `x-mb.glifo-plan` | `plan` (enum o texto; uno desconocido es el más restrictivo), `sin-texto` | Tres barras `aria-hidden` y siempre el nombre; con `sin-texto`, "Plan X" solo para el lector de pantalla |
| `x-mb.aviso` | `tono` (`info`, `exito`, `atencion`, `error`, `neutro`), `titulo`, `icono`, `anuncia`; ranura `accion` | `role="alert"` solo en `error`, `role="status"` en el resto; `:anuncia="false"` quita el rol (aviso estático) |
| `x-mb.vacio` | `titulo`, `texto`, `icono`, `nivel` (h2 a h4); ranuras `arte` y `accion` | Ícono decorativo; la ranura `arte` lo reemplaza (sin Slashi todavía) |
| `x-mb.hoja` | `titulo`, `descripcion`, `nivel`, `sin-relleno`; ranura `acciones` | Con título es una región nombrada por su encabezado |
| `x-mb.banner` | `etiqueta` (obligatoria), `tono` (`contexto` o `atencion`), `icono`; ranura `accion` | `role="region"` con `aria-label`; no se fija a la pantalla (eso es el PR 4) |

- **Nivel del plan:** `PlanRestaurante::nivel()` (1 a 3) sale de `incluye()`: sube un nivel por cada plan con estrictamente menos capacidades. No hay una segunda tabla. Las barras vacías usan `--mb-borde-control` sin opacidad (3.5:1 en claro, 4.19:1 en oscuro).
- **Tokens nuevos** (claro y oscuro, en `theme.css`): `--mb-exito|aviso|peligro|info` con `-bg` y `-borde`, y `--mb-ctx-*` del banner. `TemaDelAdminTest` lee el CSS declarado y prueba cada par (texto de estado, tinta y tinta secundaria sobre su fondo, glifo, botón y foco del banner). Los bordes de los avisos son decorativos y no se exigen.
- **Muestra:** sección "Componentes de Menubly" en la página Componentes del panel (`componentes-mb.blade.php`), solo en el entorno local. `/diseno` (el menú público) no cambió. Los textos están en `lang/es/panel.php`, clave `componentes_mb`.
- **Sin JavaScript:** ningún componente lleva `<script>` ni atributos `on*`; hay una prueba. No cambió la CSP.
- **Al escribir los usos:** una etiqueta `</x-slot:nombre>` seguida de texto sin espacio ni salto de línea rompe la compilación de Blade (`@endslotTexto`); deja siempre un salto de línea después.

### Pendiente: panel deslizable (no construido)

Ninguna pantalla lo usa todavía y el PR 2 decidió que "Más" abre el cajón de Filament. Diseño propuesto para cuando una pantalla lo necesite (`x-mb.panel-deslizable`):

- `<dialog>` nativo con `showModal()`: foco atrapado, Escape que cierra, modal implícito y capa superior (sin peleas de `z-index` con la barra inferior ni los avisos de Filament).
- Alpine estándar del admin (`x-data`, `@click`) para abrir y cerrar; clic en el fondo cierra; foco inicial en el título (`tabindex="-1"`); guardar el disparador al abrir y devolverle el foco en el evento `close`, en vez de confiar solo en el navegador.
- Dentro de una página Livewire, `wire:ignore.self` para que un re-pintado no cierre un panel abierto.
- Animación solo con `prefers-reduced-motion: no-preference`; sin desplazamiento del fondo; tamaño inferior en celular, lateral en escritorio.
- Para confirmaciones y formularios con acciones del servidor se sigue usando el modal de Filament.

### Qué probar a mano (claro y oscuro, 390 px y escritorio)

1. En local, abrir la página Componentes del panel y bajar a "Componentes de Menubly".
2. Glifo: los tres planes con y sin texto; las barras vacías se distinguen de las llenas. Con lector de pantalla se oye "Plan X".
3. Avisos: los cinco tonos y el estático; solo el de error se anuncia al aparecer; el enlace se alcanza con Tab y el foco se ve.
4. Estado vacío y hoja: el encabezado respeta el nivel; la hoja se lee como región con el título.
5. Banner: contraste y foco (anillo blanco) en contexto; el de atención sin anillo blanco; el texto largo en 390 px no desborda.

### Qué no se pudo verificar

- Nada visual ni de teclado: no hay navegador en el equipo de desarrollo.
- La página Componentes completa no se renderizó en pruebas (su tabla de ejemplo necesita la extensión `intl`, ausente aquí); se probaron las vistas de los componentes y de la muestra por separado.
