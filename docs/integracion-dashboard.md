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
| Banner "Estás viendo como superadmin" | Nada | **Nuevo** | Hoy el superadmin entra al panel de un restaurante sin banner. Se hace con un `renderHook` (`TOPBAR_BEFORE`, no BODY_START: ver sección 15) |
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
- **Pantallas no diseñadas (por revisar):** la página de Reservas en Filament (sección 17), roles distintos del propietario, plantillas A–H de la selección (solo "Plantilla" a nivel general), estados de carga y error de red en cada pantalla, impresión de QR, emails de invitación (`emails/invitacion.blade.php`).
- **Tabla/lista en celular:** el diseño usa tarjetas; Filament las soporta con columnas responsivas pero requiere trabajo por tabla.

## 8. Orden de entrega en PR pequeños

Cada PR se prueba con la suite correspondiente y, tras los PR 1–2, con revisión manual. Todo PR: `vendor/bin/pint --dirty`, `npm run build`, prueba manual en modo claro/oscuro y a 390 px. Cambios respecto a la primera versión: se añade el **PR 0** (hecho), el PR 2 pasa a ser solo el layout (la marca ya está), `/panel` deja de ser una pantalla aparte (ver sección 17: queda solo como puerta de redirección), las métricas se reducen a lo medible y Respaldos/Ajustes son de solo lectura.

| # | PR | Contenido | Probar a mano | Qué puede romperse |
|---|---|---|---|---|
| 0 | **Marca y modo claro (hecho)** | Nombre visible en `config('app.name')` (Menubly) en panel, pestañas, pie de menú y sitios, invitaciones, correos, QR imprimible, portada; sin preferencia guardada todo sale en claro; Automático/Claro/Oscuro se conservan | Ver sección 10 | Producción con `APP_NAME=MENUONL` viejo; páginas que dependían de `prefers-color-scheme` |
| 1 | **Tokens y tema (hecho, sección 11)** | Tokens `--mb-*` en el tema del admin (sin tocar `--mo-*`), fuentes por Vite, color primario, foco, 44 px. Sin mover pantallas | Login, Inicio, una tabla, un formulario, subida de foto; claro/oscuro; 390 px | Selectores `fi-*` que no existen; contraste del botón; modo oscuro compartido con el menú público; fuentes bloqueadas por CSP |
| 2 | **Layout (hecho, sección 12)** | Barra lateral y barra inferior (el logotipo sigue siendo la M hasta que llegue el arte) | Navegación en celular; enlaces "Ver mi sitio/menú" | `UxUiAccesibilidadTest` (barra inferior, "Inicio"); `PanelNavegacionDuenoTest` |
| 3 | **Componentes `x-mb.*` (hecho, sección 14)** | Glifo de plan, aviso, vacío, hoja, banner. Se muestran en la página Componentes del panel (solo local); `/diseno` no se toca | La página de componentes | `PaginasDeDisenoTest` |
| 4 | **Banner de superadmin (hecho, sección 15)** | `renderHook` + "Volver a la plataforma" | Entrar a un restaurante como superadmin y como propietario (no debe verse) | Barra pegajosa y su altura sobre la barra superior; toasts encima del banner |
| 5 | Inicio del restaurante (**cambia, sección 17**) | Rediseño de `Inicio` y widget; checklist como comanda; Slashi de chef solo con el arte oficial disponible. **Se suma** la operación del día que hoy da `/panel → Inicio` (reservas por confirmar y próximas, comensales esperados) con enlaces a la página nueva de Reservas; el resumen de pedidos queda pendiente hasta decidir Pedidos | Cada rol visible (propietario y superadmin) y cada plan; restaurante vacío | `PanelInicioTest` completo (textos y jerga); filtro por rol; el enlace de reservas de `ProgresoDeConfiguracion` |
| 6 | Resto del panel del restaurante (en tandas; **cambia, sección 17**) | Platillos/categorías, apariencia, datos/horario/contactos, QR; una tanda por grupo. **Se suman** la página de Reservas (PR 2 de la sección 17), el renombre de la página de configuración a "Ajustes de reservas y pedidos" y, al final, la auditoría campo por campo de Menú, Ajustes y Equipo de `/panel` contra Filament | Editar y guardar cada formulario; deshacer; agotado; atender una reserva del sitio | Pruebas de tablas y formularios de `UxUiAccesibilidadTest`; carga de imágenes; `PanelApiReservasTest` y `PanelPedidosYReservasTest` |
| 7 | Plataforma: restaurantes y ficha de plan | Lista, ficha, selector con "qué cambia" y "Deshacer" | Cambiar plan en ambos sentidos; reservas pendientes; suspender | `PlataformaPlanTest`, `PlanRestauranteMigracionTest` |
| 8 | Usuarios e invitaciones, historial global | Pantallas de plataforma | Invitar, reenviar, revocar; filtros | `InvitacionesTest`, `EnvioInvitacionEnColaTest`, historial |
| 9 | Respaldos y ajustes, **solo lectura** | Lista de respaldos (fecha, tipo, tamaño, estado) y valores de ajustes de plataforma; sin descargar, restaurar ni editar | Con respaldos reales en un entorno de prueba; sin respaldos | Lectura del almacenamiento; que no se filtre la contraseña de respaldos ni otros secretos de configuración |
| 10 | **Métricas: backend (hecho, sección 13)** | Contador de escaneos QR/NFC **por mesa** en `/q/*`, tabla agregada por (código, día) sin IP ni cookies. Las reservas se cuentan desde `reservas` | Escanear un QR y ver el contador; carga concurrente | `QrRedirectYDescargaTest`, `QrScanBurstConcurrencyTest`, `QrNfcVolumenYN1Test`; rendimiento de la redirección |
| 11 | Métricas: pantallas y resumen de plataforma | Resumen, métricas global/por restaurante con escaneos y reservas; **sin visitas** | Plan Completo vs otros; restaurante sin datos | Recorte por plan (`Capacidad::Metricas`); texto de privacidad cierto |
| 12 | `/panel` y pantallas de sistema (**cambia de sentido, sección 17**) | `/panel` ya no se queda: pasa a ser una puerta (302 a la página nueva dentro de `/admin`, o "sin panel" para Solo menú) y se retiran `panel.js`, `panel.css`, `panel/index.blade.php` y `/api/v1/panel`. Además 404/500, "sin panel", login. **Bloqueado** hasta decidir qué pasa con Pedidos | Flujo de invitación completo; plan "Solo menú"; marcador viejo de `/panel`; atender una reserva | `PlanSinPanelTest`, `PlanSinPanelDosFactoresTest`, `PanelApi*Test` (se migran antes de borrarlos), CSP de `/panel` e `/invitaciones` |
| L | **Logo oficial de Menubly (hecho, sección 16)** | `public/brand/`, `x-mb.logo`, `x-mb.favicons`, favicons con `?v=` | Sección 16 | `MarcaYModoClaroTest`, `LogoMenublyTest` |

**Hechos:** PR 0, 1, 2, 3, 4 y 10, y el del logo (L). Quedan 5 a 9, 11 y 12, con los cambios de la sección 17 en 5, 6 y 12.

El PR 10 se adelantó (no depende del diseño) para ir acumulando datos mientras se hace lo visual.

## 9. Decisiones aprobadas

1. **Nombre:** la plataforma se llama **Menubly**; "menuonl" era un nombre genérico provisional. El nombre visible sale de un único valor (`config('app.name')`). Se conservan los identificadores internos: nombre del repositorio, espacios de nombres, `config/menuonl.php`, comandos `menuonl:*`, claves de `localStorage` y cookies (`menuonl_cart_*`), claves de caché, nombres de base de datos, variables de entorno existentes y el dominio configurado (`CLOUDFLARE_DOMAIN`, que decide otra persona). El logotipo actual (la M) se queda por ahora.
2. **Reservas (revisada; sustituye a la decisión original "se quedan en `/panel`"):** las reservas pasan a una página de Filament dentro de `/admin`, y `/panel` deja de ser una segunda ventana: queda solo como puerta de redirección (302 a la página nueva, o la pantalla "sin panel" para Solo menú). **Motivo:** la persona dueña del producto quiere una sola ventana con el mismo diseño, navegación, banner y modo oscuro; mantener `/panel` obligaba a conservar un segundo código (JavaScript propio, una API paralela y una CSP aparte) para las mismas reglas de negocio. El plan está en la sección 17.
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

## 15. PR 4 "Banner de superadmin": qué se hizo

Un superadmin que entra a un restaurante ve arriba una franja fija: "Estás viendo como superadmin: *Nombre* (plan) [Volver a la plataforma]". Usa `x-mb.banner` (tono contexto) del PR 3.

### Quién lo ve (y quién no)

`App\Services\BannerDeSuperadmin::debeMostrarse()`: superadmin **y** dentro de un restaurante **y** sin ser miembro activo de ese restaurante **y** fuera de las pantallas de plataforma. Para cualquier otra persona el hook devuelve `''`: la vista ni se renderiza, no se esconde con CSS. Se probó con propietario, editor, gerente, recepción y cocina, y con los tres planes (Solo menú incluido: el superadmin entra igual).

- **Pantallas de plataforma:** Filament cuelga todo de `/admin/{slug}/…`, incluida la lista de Restaurantes, así que "fuera de un restaurante" no existe como ruta. Se decide por el **grupo de navegación** `Plataforma` del recurso o de la página actual (`BannerDeSuperadmin::GRUPO_PLATAFORMA`), no por una lista de rutas. Una pantalla nueva en ese grupo (historial global, respaldos, ajustes) lo oculta sin tocar este código. `UserResource` cambia de grupo según la persona ("Plataforma" para el superadmin) y se cubre igual. La prueba `test_toda_pantalla_del_grupo_plataforma_oculta_el_banner` recorre los recursos y páginas registrados del grupo y abre su lista: falla si alguno muestra el banner.
- **Superadmin que además es miembro del restaurante:** no ve el banner (está ahí como miembro, no "viendo como superadmin").
- **Enlace:** `RestauranteResource::getUrl('index')`. Ocultar el botón no es seguridad: la lista la protege `RestaurantePolicy::viewAny` (solo superadmin; los demás roles reciben 403, probado). No hay sesión de "restaurante activo" que limpiar: el restaurante sale de la URL.

### Decisión: no hay impersonación

No existe ningún "entrar como" (ni paquete ni código). El superadmin entra a un restaurante navegando a su `slug` con permiso de superadmin; sigue siendo él mismo y el historial lo registra como él. **Si alguien añade una impersonación, debe revisar este banner primero:** hará falta distinguir "soy el superadmin" de "actúo como otra persona", un botón para salir de esa sesión y una decisión sobre quién ve el banner mientras dura. Hasta entonces, no la añadas.

### Dónde se engancha y por qué

`PanelsRenderHook::TOPBAR_BEFORE`. El prototipo (y la sección 2.2 de este doc, ya corregida) decía `BODY_START`, pero ese hook cae **antes** del enlace "Saltar al contenido". Con `TOPBAR_BEFORE` el orden de foco es: "Saltar al contenido", banner (enlace "Volver a la plataforma"), barra superior, menú, contenido. Una prueba comprueba ese orden en el HTML.

### Layout

- **Token:** `--mb-banner-alto: 3.25rem` (52 px, fijo). El banner es de una sola línea con altura exacta, para poder compensar el resto sin medir nada con JavaScript: `position: sticky; top: 0; z-index: 40` (por encima de la barra superior y el menú, z 30; por debajo de modales y avisos, z 50).
- **Compensaciones** (barra superior, menú lateral, cajón del menú en celular, `scroll-padding-top` y los avisos de Filament): todas cuelgan de `.fi-body:has(.mb-banner--fijo)` y usan `--mb-banner-activo`, que solo existe cuando el banner está renderizado. Para los demás roles no se aplica ninguna regla: ni un píxel de cambio. Una prueba lee el CSS y falla si una regla de compensación pierde esa condición.
- **Avisos (toasts):** su `top` baja la altura del banner mientras el banner está activo, para no tapar "Volver a la plataforma".
- **Barra inferior móvil y acciones pegajosas de formularios:** no cambian; están ancladas abajo.
- **390 px:** el nombre del restaurante se corta con puntos suspensivos; el prefijo pasa a "Superadmin:", el botón a "Volver" (el nombre accesible sigue siendo "Volver a la plataforma") y el nombre del plan queda solo para el lector de pantalla. El nombre **completo** va en el `aria-label` de la región y en `title`.

### Accesibilidad

`role="region"` con `aria-label` ("Vista de superadmin del restaurante :nombre"), foco visible de 3 px (anillo blanco), contrastes de la sección 14 (texto 11.34 claro / 8.19 oscuro; botón 6.59; foco 12.46 / 8.99; ya probados en `TemaDelAdminTest`). El banner no se anima, así que `prefers-reduced-motion` no tiene nada que apagar.

### Pruebas

`BannerDeSuperadminTest` (21): visible en los tres planes, ausente en el HTML de los cinco roles, superadmin miembro, sin restaurante, nombre largo o con marcas escapado, sin scripts ni `on*`, textos sin emojis ni "carta", todas las pantallas del grupo Plataforma, orden de foco, `Cache-Control` privado (no cacheable), misma CSP con y sin banner, destino protegido por política y CSS condicionado.

### Qué probar a mano (390 px y escritorio, claro y oscuro)

1. Superadmin en un restaurante Completo, Sitio y menú y Solo menú: el banner aparece con el glifo del plan.
2. Propietario: no hay banner ni hueco arriba. Compara la altura de la barra superior con la de antes del PR.
3. Superadmin en Restaurantes y Cuentas (Plataforma): no hay banner.
4. Desplazar la página: el banner y la barra superior se quedan fijos y no se montan uno sobre otra.
5. 390 px: nombre largo cortado con puntos suspensivos, "Volver" siempre visible; abrir el menú ("Más"): el cajón empieza bajo el banner.
6. Escritorio: el menú lateral empieza bajo la barra superior y no queda cortado abajo.
7. Provocar un aviso de Filament (guardar un formulario): no tapa el botón del banner.
8. Teclado: primer Tab = "Saltar al contenido", segundo = "Volver a la plataforma"; el anillo blanco se ve.

### Qué no se pudo verificar

- Nada visual ni de teclado: no hay navegador en el equipo de desarrollo. Las compensaciones del menú lateral y del cajón dependen de reglas de Filament (`--topbar-height` de 4rem, `.fi-sidebar`, `.fi-no`) leídas del CSS del paquete, no vistas en pantalla.
- Un lector de pantalla real.

## 16. PR "Logo oficial de Menubly": qué se hizo

Se reemplazó la "M" de relleno por el logo oficial (paquete `menubly-logos`, solo lectura, fuera del repositorio) sin rediseñar pantallas. No se tocó la lógica de planes, métricas ni permisos, ni la CSP, ni ningún identificador interno (`menuonl:*`, `config/menuonl.php`, `menuonl_cart_*`).

### Archivos (solo los que se usan; no se copiaron carpetas)

`public/brand/`: `menubly-horizontal-color.svg` y `-negativo.svg` (4.7 KB), `menubly-wordmark-color.svg` y `-negativo.svg` (2.4 KB), `menubly-simbolo-color.svg` (2.0 KB), `favicon.svg` (1.6 KB), `favicon.ico` (3.7 KB, 16 y 32 px) y `apple-touch-icon.png` (4 KB, 180 px). Además `public/favicon.svg` y `public/favicon.ico` (copias idénticas, en la raíz porque los navegadores piden `/favicon.ico` solos; una prueba comprueba que coinciden con los de `brand/`). No se copiaron las variantes `-navy` ni `-blanco` (estas usan `<mask>`), el vertical, el símbolo pequeño, los PNG por tamaño, 05-iconos-app, 07-redes ni 08-slashi-chef (la mascota va en el PR de Inicio).

### Seguridad de los SVG

Se revisaron todos: sin `<script`, atributos `on*`, `foreignObject`, `href`/`xlink:href`, `@import`, `url(`, `<image`, `data:`, `<style` ni `<use`. Son solo `rect`, `circle`, `g` y `path` con `fill` fijo. No se optimizaron (ya pesan entre 1.6 y 5 KB; no valía el riesgo de alterar el dibujo). Se cargan siempre con `<img>`, nunca en línea. La prueba `test_ningun_svg_tiene_codigo_activo_ni_referencias_externas` lo vigila para todo `public/brand/*.svg` y `public/favicon.svg`, así que un SVG nuevo con `-blanco` (que lleva `url(#...)`) la haría fallar a propósito.

### Componentes

- `x-mb.logo` (`variante`: horizontal, wordmark o simbolo; `alto` en px; `decorativo`). Calcula el ancho con la proporción del SVG y escribe **siempre** `width` y `height` (los del SVG, de 2440 px, nunca se usan). `alt` es `config('app.name')`, nunca el literal; con `decorativo` queda vacío. Cada archivo lleva `?v=` con la fecha del archivo.
- `x-mb.favicons`: `favicon.svg`, `favicon.ico` y `apple-touch-icon`, los tres con `?v=filemtime`. Lo usan `x-layout.publico`, los cinco layouts de sitios y plantillas, `panel/index` y `plan/sin-panel`. En Filament, `->favicon()` lleva el mismo `?v=` y un hook `HEAD_END` añade el `.ico` y el de Apple.

### Claro y oscuro (sin JavaScript, sin scripts en línea)

Dos `<img>`: la de modo claro (por defecto) y la de modo oscuro, esta con `hidden` y `loading="lazy"` (no se descarga si no se ve). El símbolo es el mismo en los dos modos, así que lleva una sola. Las reglas viven en un solo sitio por superficie:

- Público (`public/css/menuonl.css`): `data-apariencia="dark"`, y `data-apariencia="system"` dentro de `@media (prefers-color-scheme: dark)`. Sin preferencia guardada se ve el logo claro.
- `/admin` (`resources/css/filament/admin/theme.css`): clase `.dark`.
- `plan/sin-panel`: su propio `<style>` (ya era autónomo, no carga `menuonl.css`).

### Dónde aparece

Portada y invitaciones (horizontal, 32 px, reemplaza la "M" y el nombre en texto; el enlace conserva su `aria-label`), login, barra lateral y barra superior de Filament (`filament/logo.blade.php`, horizontal 32 px), pantalla "sin panel" (horizontal 32 px) y pie del menú público ("Hecho con" más el wordmark de 16 px; el `alt` aporta el nombre). Favicon de Menubly en todas las pestañas, también en sitios y plantillas de restaurantes (como antes).

### Lo que quedó fuera y por qué

- **Pies de sitios** ("Sitio creado con...") y pantalla "Menú no disponible": no se pidieron en este PR; siguen con texto.
- **QR imprimible:** ahí va el logo del restaurante, no el de la plataforma.
- **Correos de invitación:** un correo no puede cargar de forma segura un logo del servidor local (se bloquea o se pierde); se resuelve aparte, con imagen alojada o incrustada, y una decisión sobre privacidad.
- **`og:image`, `theme-color` y PWA** (manifiesto, `icon-192/512`, maskable): no hay manifiesto hoy y el azul bebé choca en modo oscuro; no se justifican todavía. El `head-snippet.html` del paquete además usa un dominio de ejemplo.

### Caché

Los iconos y logos se piden con `?v=` y estáticos desde `public/` (sin sesión ni cookies). La ruta `/favicon.ico` de la raíz sin `?v=` solo se renueva con purga: ver el paso 9 de "Cada despliegue siguiente" en `docs/despliegue.md` (purgar `/brand/*` y `/favicon*` en Cloudflare tras desplegar). El peso total del logo en el menú público móvil es de 2.4 KB (wordmark).

### Pruebas

Nuevas (`LogoMenublyTest`, 8): archivos existentes y copias de la raíz idénticas, SVG sin código activo ni referencias externas, `width`/`height` y `alt` del componente, reglas de claro/oscuro en cada hoja, logo en login, portada, invitaciones, pie del menú y "sin panel", favicons con `?v=` en las pestañas y los layouts de sitios, ninguna imagen ni icono de otro origen. Se actualizó `MarcaYModoClaroTest` (el pie del menú ahora comprueba el `alt`).

### Qué probar a mano (390 px y escritorio, claro y oscuro)

1. Login, barra lateral y barra superior de `/admin`: logo nítido, sin salto de diseño; cambiar a oscuro cambia a la variante negativa.
2. Portada e invitación: logo en lugar de la "M"; enlace a la portada con foco visible.
3. Menú público: el wordmark del pie junto a "Hecho con", en claro, oscuro y "Automático" con el sistema en oscuro.
4. Pantalla "sin panel" (cuenta con plan sin panel).
5. Pestaña del navegador: favicon nuevo (puede tardar por la caché; probar en ventana privada y `/favicon.ico` directo). Agregar a la pantalla de inicio en iOS (apple-touch-icon).
6. Pestañas de un sitio de restaurante: favicon de Menubly.

### Qué no se pudo verificar

- Nada visual: no hay navegador en este entorno; el aspecto del logo, el cambio de variante y las pestañas no se vieron en pantalla (las pruebas comprueban el HTML y el CSS, no el pintado).
- No se abrieron el `.ico` ni el PNG uno a uno más allá de comprobar tamaño y tipo.

## 17. Unificar `/panel` dentro de `/admin`: plan (solo análisis, sin código)

Estado: **análisis aprobado; no hay código ni pruebas escritas.** Revierte la decisión original 2 de la sección 9 ("las reservas se quedan en `/panel`"), que ahora está reescrita allí. La dueña o el dueño del producto quiere eliminar la segunda ventana "Pedidos y reservas de hoy" y que todo viva dentro de `/admin`, con el mismo diseño, navegación y banner. Ya están hechos los PR 0 a 4, el 10 y el del logo (sección 16).

### 17.1 Hallazgo que fija el alcance

`/panel` no es solo "Pedidos y reservas de hoy". Es una aplicación de una sola página (`resources/js/panel.js`, 298 líneas, sin framework; `resources/css/panel.css`; `panel/index.blade.php`) con seis funciones, servida por la API `/api/v1/panel` (siete controladores, 807 líneas). La página de Filament que hoy se llama "Pedidos y reservas" (`PedidosYReservas`) es otra cosa: solo guarda configuración.

| Función de `/panel` | Qué hace | Equivalente en Filament hoy | Destino |
|---|---|---|---|
| Inicio | Ventas del día, comensales esperados, por confirmar, pedidos activos y tarde, próximas reservas, platillos agotados | `Inicio` y su widget (sin reservas ni pedidos en vivo) | PR 3 |
| Reservas | Próximas, Hoy y por fecha; confirmar, "Llegó", cancelar, restaurar, mover de hora, crear, agrupar por día | Ninguno (no hay recurso de Reserva) | PR 2 |
| Pedidos | Tablero Nuevo, En cocina, Listo y Entregado; avanzar con un toque; crear pedido a mano; marca "tarde" a los 15 min | Ninguno | **Pendiente** (17.5) |
| Menú | Editar nombre y precio al salir del campo, disponible, ocultar, duplicar, eliminar, subir y quitar foto (con reducción en el navegador) | `ProductoResource` y `CategoriaResource` (formulario, no edición rápida) | Auditoría (PR 6) |
| Ajustes | Plantilla del sitio, portada y galería, datos, horarios, reservas del sitio (cupo y franja) | `Apariencia`, `FotosDelRestaurante`, `EditRestauranteProfile`, `HorarioResource`, `PedidosYReservas` | Auditoría (PR 6) |
| Equipo | Cambiar rol, activar o desactivar, invitar y revocar | `UserResource` e `InvitacionResource` (no auditado a fondo) | Auditoría (PR 6) |

Datos que importan para el plan:
- El único "tiempo real" es un sondeo de 30 s sobre reservas, pedidos y resumen. Se salta si la pestaña está oculta, si hay un campo con foco o si hay un diálogo abierto.
- Los pedidos solo se crean a mano desde `/panel`. Ningún flujo público los guarda: los pedidos del sitio salen por WhatsApp. Las reservas sí llegan del sitio público (`ReservasPublicas`) y también se crean a mano.
- Módulos por rol y por plan salen de `PermisosPanel` (rol ∩ plan): Completo = todos; Sitio y menú = solo reservas; Solo menú = ninguno. Se aplican con `ModuloPanel` y las políticas.
- La pantalla "tu plan no incluye el panel" la sirve la ruta `/panel` (403, `no-store`) para quien solo pertenece a restaurantes de Solo menú. Como `canAccessPanel` les niega Filament, esa pantalla solo existe porque `/panel` existe.

### 17.2 Decisiones aprobadas

1. **Menú, Ajustes y Equipo se retiran al final**, después de una auditoría campo por campo contra Filament.
2. **Recepción y cocina siguen ocultos.** Solo propietario y superadmin son visibles. La invitación (en Filament y en `/panel`) ofrece solo "propietario" por ahora; los demás roles (gerente, editor, recepción, cocina) **quedan en el código**: `PermisosPanel::MODULOS_POR_ROL`, `PermisosPanel::rolesAsignables`, las políticas, `User::esSoloOperativo()` y `LoginRespuesta`. No hay PR de "roles operativos". Hoy el código ya los ofrece (`rolesAsignables` devuelve los cinco y la pantalla Equipo de `/panel` los muestra), así que ocultarlos es un cambio de comportamiento, con sus pruebas (`PanelEquipoTest`, `InvitacionesTest`, `PanelRolesOperativosTest`).
3. **Pedidos manuales: no se migran todavía.** La decisión (migrar o retirar) queda pendiente; ver 17.5.
4. **`/panel` queda como puerta de redirección:** 302 a la página nueva dentro de `/admin/{slug}`, o la pantalla "sin panel" para quien solo tiene restaurantes en Solo menú. Se conserva `route('panel')` para marcadores y enlaces viejos.
5. **La página de configuración "Pedidos y reservas" se renombra a "Ajustes de reservas y pedidos"**, en el PR de navegación (PR 3), con sus pruebas, para que no se confunda con las páginas operativas.

### 17.3 Arquitectura elegida

Opción A (páginas de Filament/Livewire que reutilizan servicios) ejecutada por etapas (opción D), con `/panel` vivo hasta el final.
- **Servicios compartidos:** la lógica de reservas (y de pedidos, si se migran) sale de los controladores a servicios que usan a la vez la API y las páginas. Mientras `/panel` exista, la API no cambia de comportamiento.
- **Permisos:** cada página nueva decide quién la ve con `PermisosPanel::permite()` (mismo techo de plan y de rol) y la política del modelo; no se duplica la regla.
- **CSP:** sin cambios. `/admin` ya lleva `script-src 'self' 'nonce-…' 'unsafe-eval'`, así que las páginas nuevas no necesitan ninguna excepción.
  - Se pierde la CSP estricta de `/panel` (sin `eval`) para estas funciones. El riesgo marginal es bajo: la misma sesión ya tiene el mismo poder en `/admin`.
  - Se gana: Blade escapa por defecto (hoy `panel.js` escribe `innerHTML` con un `esc()` manual), una sola capa de autorización y ya no se lee la cookie `XSRF-TOKEN` desde JavaScript.
  - La política `panel()` **no se borra**: la sigue usando `/invitaciones/*`.
  - Quitar `unsafe-eval` del admin sigue siendo el pendiente de `docs/despliegue.md` (6.1); esta migración no lo agrava ni lo resuelve.
- **Operación en vivo:** `wire:poll` solo en las páginas de reservas (y pedidos, si se migran), con la pestaña visible y sin pisar un campo o modal abierto (hoy esa lógica está a mano). Cada ciclo es una petición Livewire (más pesada que un JSON). Las peticiones de Livewire no pasan por `throttle:panel-api`; hay que decidir si necesitan un límite propio.

### 17.4 PR en orden

| # | PR | Contenido | Pruebas | Riesgos |
|---|---|---|---|---|
| 1 | **Base de servicios (hecho, 17.9)** | Extraer de `ReservaController` (y `ResumenController`) servicios de reservas sin cambiar comportamiento. Ocultar roles distintos de propietario en la invitación (Filament y API) según la decisión 2 | `PanelApiReservasTest` y `PanelApiMenuYResumenTest` pasan intactas; `PanelEquipoTest`, `InvitacionesTest` y `PanelRolesOperativosTest` se ajustan a "solo propietario visible" | Que la extracción cambie una regla (estados, zona horaria, tope de 200 próximas); que ocultar roles rompa invitaciones ya enviadas con otro rol |
| 2 | **Reservas en Filament (hecho, sección 18)** | Página con Próximas, Hoy y Fecha, todas las acciones de `/panel` y `wire:poll`. Con el mismo tope de plan (Sitio y menú la ve; Solo menú no). `/panel` sigue vivo | Pruebas Livewire de la página por rol y plan; reglas de `PanelApiReservasTest` y `PanelPlanAccesoTest` repetidas sobre la página (aislamiento entre restaurantes, 403 por rol) | Peso del sondeo en tablet y celular; no pisar un campo en edición; sesión caducada a mitad de turno |
| 3 | **Navegación, Inicio, barra inferior y renombre** | Ítem y acceso "hoy" de `BarraInferiorDelPanel` y "Panel de servicio" apuntan a la página nueva; enlace de reservas de `ProgresoDeConfiguracion`; Inicio con la operación del día (sin pedidos). Renombre a "Ajustes de reservas y pedidos" (`lang/es/panel.php`, claves `panel.pedidos.*`) | `BarraInferiorDelPanelTest`, `PanelInicioTest`, `PanelPedidosYReservasTest`, `PanelTextosDelDuenoTest`, `UxUiAccesibilidadTest` | Textos que otras pruebas buscan literalmente; el grupo de navegación `panel.grupos.pedidos` |
| 4 | **Plataforma** (PR 7, 8 y 9 de la sección 8) | Sin cambios respecto a la sección 8 | Las de la sección 8 | Las de la sección 8 |
| 5 | **Métricas: pantallas** (PR 11 de la sección 8) | Sin cambios; las reservas se cuentan desde `reservas` | Las de la sección 8 | Las de la sección 8 |
| 6 | **Auditoría de Menú, Ajustes y Equipo y retiro de `/panel` y `/api/v1/panel`** | Primero la auditoría campo por campo contra Filament (edición rápida de precio y disponibilidad, ocultar y duplicar, foto con reducción en el navegador, plantilla, portada y galería, datos, horarios, reservas del sitio, equipo con solo propietario visible). Cubrir las brechas. Luego: `/panel` pasa a puerta (302 o "sin panel"), se borran `panel.js`, `panel.css`, `panel/index.blade.php` y los controladores y rutas de `/api/v1/panel` | Migrar antes de borrar: `PanelApiReservasTest` (13), `PanelPlanAccesoTest` (13), `PanelAjustesTest` (9), `PanelImagenesTest` (9), `PanelRolesOperativosTest` (7), `PanelEquipoTest` (7), `PanelApiMenuYResumenTest` (7) y `PanelApiPedidosTest` (6, según 17.5). Ajustar `PlanSinPanelTest` (18) y `PlanSinPanelDosFactoresTest` (6), `CabecerasDeSeguridadTest` (la prueba de la CSP de `/panel`), `ErroresDeSubidaDeImagenTest` e `ImagenesSeguridadTest` | **Bloqueado hasta decidir Pedidos (17.5).** Perder cobertura de aislamiento entre restaurantes si se borran las pruebas de la API antes de migrarlas; que Solo menú pierda su pantalla "sin panel"; marcadores viejos de `/panel` |
| 7 | **Pedidos** | **Pendiente** de decisión | Ver 17.5 | Ver 17.5 |

Los PR 4 y 5 de esta tabla son los PR 7 a 9 y 11 de la sección 8: se mantienen en ese orden relativo, después del 3 y antes del retiro.

### 17.5 Pedidos: pendiente, y qué haría falta en cada caso

`/panel` no puede apagarse del todo mientras Pedidos exista solo ahí. Por eso el retiro (PR 6) está bloqueado hasta decidir.

**Si se migran:**
- Una página de Filament con el tablero de cuatro estados (en celular, una pestaña por estado, objetivo táctil de 44 px) y el alta de un pedido a mano.
- Extraer a un servicio la regla de `PedidoController` (estado siguiente con `Pedido::siguienteEstado()`, `entregado_at`, los entregados del día en la zona horaria del restaurante).
- Sondeo propio (probablemente más corto que el de reservas), visible y sin pisar un campo en edición.
- Quién la ve: hoy el módulo es de propietario, gerente, editor y cocina, y solo en el plan Completo (D7 de `docs/planes.md`). Con los roles ocultos, la ven propietario y superadmin.
- Pruebas: `PanelApiPedidosTest` (6) pasa a pruebas de la página; `PanelPlanAccesoTest` cubre D7.

**Si se retiran:**
- Quitar el módulo `pedidos` de `PermisosPanel::MODULOS`, las rutas y el controlador de pedidos de la API, y los campos de pedidos del resumen (`pedidos_activos`, `ventas_hoy`) y de Inicio.
- Decidir qué pasa con el modelo y los datos: `Pedido`, `PedidoPolicy`, su factory y migración, y las filas existentes (conservarlas o borrarlas con una migración). Es una decisión de datos, no solo de pantalla.
- Actualizar D7 de `docs/planes.md`, las pruebas que citan pedidos y los textos de Inicio.
- Nota: "Pedidos por WhatsApp" (envío desde el menú público) **no se toca**; es otra función.

### 17.6 Redirecciones y enlaces

- `/panel` conserva su nombre de ruta. Para quien tiene al menos un restaurante con panel responde 302 a la página nueva del restaurante activo; para quien solo tiene Solo menú, muestra "sin panel".
- Cuatro puntos del código apuntan hoy a `route('panel')`: `LoginRespuesta`, el ítem "Panel de servicio" (`AdminPanelProvider`), el acceso `hoy` de `BarraInferiorDelPanel` y el enlace de reservas de `ProgresoDeConfiguracion`. No hay enlaces a `/panel` en las plantillas de correo. Pasan a la página nueva en el PR 3.
- `LoginRespuesta` y `User::esSoloOperativo()` se **conservan** (decisión 2); mientras los roles operativos estén ocultos no habrá cuentas que los usen.
- Las reglas de Cloudflare para `/panel*` (`docs/despliegue.md`) siguen valiendo mientras exista la puerta.

### 17.7 Pruebas manuales (en cada PR que toque pantalla)

Propietario y superadmin, en cada plan (Solo menú debe ver "sin panel"). Reserva que llega del sitio público y se confirma, se mueve de hora y se cancela. Dos pestañas abiertas con el sondeo, sin que se pise un campo en edición. Sesión caducada a mitad de turno. Marcador viejo de `/panel`. 390 px y escritorio, en claro y oscuro. Con el banner de superadmin dentro de un restaurante.

### 17.8 Qué no se pudo verificar

- **Nada visual ni de teclado:** no hay navegador en el equipo de desarrollo; el análisis salió de leer el código.
- **Livewire 4.4 y Filament 5.10:** no se consultó la documentación de `wire:poll` (opciones de visibilidad) ni de páginas con tenant; se hará con `search-docs` al empezar el PR 2.
- **Equivalencia de Menú, Ajustes y Equipo:** solo se compararon nombres y endpoints, no campo por campo; esa es la auditoría del PR 6.
- **Límite `panel-api`:** no se abrió; no se sabe si habría que replicarlo para Livewire.
- **Rendimiento del sondeo en una tablet real:** sin medir.
- **Invitaciones en Filament:** no se comprobó si `InvitacionResource` ofrece todos los roles o solo algunos; el PR 1 debe empezar por ahí.

### 17.9 PR 1 "Base de servicios de reservas y roles ofrecidos": hecho

La API `/api/v1/panel` y `/panel` se comportan igual (mismo orden de errores, 403 antes que 422; misma forma del JSON; mismos 404). Sus pruebas pasan sin cambios. No se crearon páginas de Filament ni se tocó Pedidos.

**Servicios** (`app/Services/`):
- `ReservasDelDia`: `hoy()` (fecha en la zona del restaurante), `delDia()`, `proximas()` (tope `MAXIMO_PROXIMAS` = 200), `resumenDelDia()` y `porConfirmarDesdeHoy()`. Sustituyen tres cálculos repetidos (`ReservaController`, `ResumenController` y `ProgresoDeConfiguracion`). Cada consulta filtra además por `restaurante_id`, así que es segura sin contexto activo.
- `GestionDeReservas`: `crear`, `actualizar`, `cambiarEstado` (con los atajos `confirmar`, `marcarLlegada`, `cancelar` y `restaurar`), `mover` y `eliminar`. Cada operación sigue el mismo orden: módulo por rol y plan (`PermisosPanel`), pertenencia al restaurante (404 si es ajena), política (403), validación (422) y escritura. Las reglas de validación viven en `GestionDeReservas::reglas()`. Un restaurante distinto del contexto activo no guarda nada.
- `ConflictoDeReserva`: se lanza cuando el valor esperado ya no coincide.

**Concurrencia.** Eloquent escribe solo las columnas modificadas, así que dos personas que cambian campos distintos no se pisan (hay una prueba). Quien necesite proteger un mismo campo pasa lo que vio en pantalla (`estado`, `hora` o `fecha`): la escritura es un único `UPDATE ... WHERE` con ese valor y, si no coincide, lanza `ConflictoDeReserva` en lugar de pisar el cambio ajeno. La API actual nunca lo usa; lo usarán las páginas de Filament (PR 2). El panel sigue sin mirar el cupo de la franja ni las fechas pasadas (solo el sitio público lo hace), y el cupo del sitio sigue serializado con `lockForUpdate` sobre el restaurante.

**`whereDate` se conserva.** Se comprobó cómo guarda `fecha` el cast de Eloquent en SQLite: un texto `2026-10-26` se guarda tal cual, pero un `Carbon` se guarda como `2026-10-26 00:00:00`. Una igualdad directa (`where('fecha', ...)`) solo encontraba 2 de 5 filas creadas de distintas formas; `whereDate` encuentra las 5. En MariaDB no se pudo comprobar, así que no se cambió. Las pruebas de `ReservasDelDiaTest` escriben de tres formas (texto, `Carbon` y inserción directa) y esperan las mismas filas.

**Roles ofrecidos.**
- `PermisosPanel::ROLES_OFRECIDOS = ['propietario']` es el único interruptor. `rolesAsignables()` (a quién se puede dar un rol nuevo) es ahora `rolesModificables()` (a quién se puede gestionar) ∩ los ofrecidos; un gerente no puede dar propietario, así que no puede invitar.
- El servidor rechaza un rol no ofrecido: `InvitacionService::invitar` lanza `ValidationException` (422 en la API; en Filament el formulario ya lo rechaza como error del campo `rol`, y el servicio es la última defensa). La API de equipo rechaza `rol` con 422, pero sigue permitiendo desactivar y reactivar a miembros con cualquier rol (antes quedaban fuera por usar una sola lista para las dos cosas).
- El servidor acepta convertir a un miembro existente en propietario. La interfaz no muestra el selector de rol mientras se ofrezca un solo rol, y oculta el formulario de invitar si no hay roles asignables. Ese es el único cambio de `panel.js`.
- Siguen funcionando: los usuarios con otros roles, sus módulos y las invitaciones ya emitidas con otro rol (se aceptan hasta que caducan a los 7 días).

**Pendientes y riesgos anotados:**
- **Último propietario activo (riesgo pendiente).** Hoy la API de equipo no lo protege: el superadmin puede desactivar al único propietario activo de un restaurante (`PATCH .../equipo/usuarios/{id}` con `activo: false` responde 200), y entonces `rolEn()` ya no devuelve propietario para nadie. Un propietario no puede desactivarse a sí mismo (422). La acción "Quitar acceso" de Filament sí lo protege (`UserPolicy::revocarAcceso`), aunque su conteo no distingue propietarios inactivos. No se cambió; hay una prueba que solo documenta el comportamiento (`RolesOfrecidosTest`). Decidir en otro PR si se bloquea (y qué mensaje).
- **Texto del correo de invitación (pendiente menor).** `InvitacionMail` muestra "Propietario" o "Editor" según el rol, así que para gerente, recepción o cocina diría "Editor". No se tocó; no afecta mientras solo se ofrezca propietario.
- **Historial de reservas:** no existía; lo resolvió el PR 2 con la tabla `reserva_eventos` (sección 18).

**Qué no se pudo verificar:** MariaDB real (las pruebas usan SQLite en memoria; `lockForUpdate` no hace nada ahí, y la comparación de `fecha` se comprobó solo en SQLite), peticiones realmente simultáneas (la concurrencia se simula con instancias antiguas del mismo registro) y el aspecto de `/panel` en un navegador (solo se comprobó la sintaxis del JavaScript).


## 18. PR 2 "Reservas en Filament": hecho

La página **Reservas** vive en `/admin/{slug}/reservas` (clase `App\Filament\Pages\Reservas`). `/panel`, `/api/v1/panel` y la CSP no se tocaron; sus pruebas pasan sin cambios.

### Qué hace

- **Pestañas:** Próximas (agrupadas por día, tope de 200), Hoy y "Ver otro día". Viven en la URL (`?vista=` y `?fecha=`), así que recargar o volver atrás las conserva. Una vista o fecha inventada vuelve a Próximas.
- **Acciones:** agregar, confirmar, "Llegó", cancelar (con confirmación), restaurar y mover de hora **y de fecha** (`/panel` solo movía la hora). Eliminar quedó fuera.
- **Historial:** acción "Historial" por reserva (quién, qué y cuándo, en la zona del restaurante) y una línea en la tarjeta con el último cambio.
- **Navegación:** grupo "Pedidos y reservas", orden 5, ícono `OutlinedCalendarDays`, con insignia de las reservas por confirmar desde hoy. La ve quien tiene el módulo `reservas` en `PermisosPanel` (rol por plan): Completo y Sitio y menú sí, Solo menú no (403 en la ruta, oculta en el menú). El superadmin la ve si el plan incluye reservas. El banner de superadmin y el modo oscuro vienen del layout.
- **Diseño:** vista Blade propia con `x-mb.hoja`, `x-mb.vacio` y `x-mb.aviso`; acciones y ventanas estándar de Filament. Tarjetas en 390 px y fila en pantallas anchas (`resources/css/filament/admin/theme.css`, clases `mb-reserva*`). No se usó la tabla de Filament: con `records()` habría que reimplementar la consulta, el agrupado y el tope que ya da `ReservasDelDia`.
- **Textos:** todos en `lang/es/panel.php` (`panel.reservas.*`). Los mensajes de validación salen de `lang/es/validation.php`, que ya trae las reglas que usa el servicio (`required`, `string`, `integer`, `between`, `max`, `date_format`, `in`).

### Seguridad de las acciones

Todo cambio pasa por `GestionDeReservas` (módulo por rol y plan, pertenencia al restaurante, política y validación). Lo que llega del navegador no es de confianza:

- Cada botón manda un solo argumento escalar: `wire:click="mountAction('cancelar', { reserva: 12 })"`. El id se busca siempre dentro del restaurante actual; una reserva ajena, inexistente o con un id que no es número responde como "esa reserva ya no existe" y no escribe.
- El estado, la hora y la fecha se leen del servidor a partir del id (el servicio sigue usando transacción y `lockForUpdate`). Lo demás que mande el navegador se ignora.
- Los datos de una ventana (hora, fecha, nombre...) los valida el servicio; sus errores salen en los campos de la ventana.
- Sin rol o con plan Solo menú (también si cambian con la página abierta) el servicio lanza 403 y no se escribe. Cada render y cada sondeo vuelven a comprobar el acceso.
- Las pruebas de manipulación están en `ReservasPaginaTest`.

### Historial (`reserva_eventos`)

- Tabla propia, escrita por `GestionDeReservas` en la **misma transacción** que el cambio (si falla el evento se deshace el cambio; un conflicto no deja evento). Guarda: restaurante, id de la reserva (sin llave foránea, para que sobreviva si se borra), usuario, acción (`creada`, `confirmada`, `llego`, `cancelada`, `restaurada`, `movida`, `eliminada`), origen y los valores de estado, hora y fecha **solo de lo que cambió**.
- **No guarda** nombre, teléfono, nota ni personas. No usa `Auditable`, así que no toca la caché del menú ni `historial_cambios`.
- **Origen:** `admin` (esta página), `api` (por omisión; es lo que usa hoy el controlador de `/api/v1/panel`, y por tanto `/panel`) o `panel` (reservado). El controlador no se tocó, así que `/panel` queda registrado como `api`; para distinguirlo bastaría pasar `panel` desde `ReservaController` en el PR de retiro.
- **Cambió el servicio:** las firmas de `GestionDeReservas` ganaron un último parámetro opcional `$origen` y las escrituras se hacen en una transacción que primero lee la fila con `lockForUpdate`. El comportamiento visible de la API es el mismo.
- Las reservas que llegan del sitio público (`ReservasPublicas`) no pasan por el servicio y no generan evento; el primer evento aparece cuando alguien las atiende.
- **Retención:** 12 meses, configurable en `config/menuonl.php` (`reservas.eventos_retencion_meses`, variable `RESERVAS_EVENTOS_RETENCION_MESES`, mínimo 1). `menuonl:purgar-reserva-eventos` borra en lotes de 1000 y está programado a las 04:50, sin solaparse (`withoutOverlapping`): el respaldo va a las 04:00 por omisión y la purga de escaneos a las 04:30.
- **Respaldo y restauración:** `RespaldoService` vuelca la base completa con `mysqldump --single-transaction` (sin `--ignore-table`) o copia el archivo SQLite, y `RestauracionService` mueve todas las tablas de la base. La tabla nueva entra sin cambios. No se probó con MariaDB real.

### Tiempo real

- `wire:poll.30s.visible="refrescar"` en el contenedor de la lista. Cada ciclo es una petición Livewire que re-renderiza siempre (máximo 200 filas); no hay huella de cambios, por decisión.
- **Con una ventana abierta (agregar, mover, cancelar, historial) el servidor no devuelve HTML** (`skipRender`), así que no se pisa lo que se escribe. El atributo `wire:poll` no sirve para esto: Filament pinta las ventanas como una parcial aparte y el atributo de la vista no se entera.
- Livewire 4.4.8 baja el ritmo a ~5 % de los ciclos en una pestaña oculta (no la detiene), pausa `.visible` fuera de pantalla y se pausa solo sin conexión o tras un 419.
- **Lector de pantalla:** una región `role="status"` que solo se llena cuando un sondeo trae más reservas por confirmar que antes; un sondeo sin cambios no anuncia nada.
- **Punto a vigilar:** las peticiones de Livewire **no pasan por `throttle:panel-api`** (240/min por usuario, solo para la API). No se añadió un límite propio: el sondeo legítimo es de ~2 peticiones por minuto por pestaña, y un límite afectaría a todo `/admin`. Vigilar en producción el volumen de `POST /livewire/update` por usuario y el tiempo de respuesta con 200 reservas; si hace falta, el siguiente paso es un limitador por usuario en las rutas de Livewire o una huella de cambios para saltarse el render.

### Conflictos

Como el navegador solo manda el id, confirmar, "Llegó", restaurar y cancelar **ya no detectan** que la pantalla estaba desactualizada: parten del estado real de la reserva en el momento del clic (el sondeo mantiene la lista al día). **Mover** sí detecta el cambio de otra persona con la ventana abierta: la hora y la fecha que se vieron al abrirla las guarda el servidor al montar la ventana; si cambiaron, la ventana sigue abierta con lo que se eligió, el aviso dice cómo está ahora y esos valores pasan a ser los actuales.

### Sesión expirada y errores de red

Un `@script` en la vista (con el nonce de Livewire; sin cambio de CSP) registra `Livewire.interceptRequest`. Esa API se comprobó en el JS instalado (`vendor/livewire/livewire/dist/livewire.js`): el objeto `Livewire` trae `interceptRequest`, el interceptor recibe `onError({ response, body, preventDefault })`, `onFailure` y `onSuccess`, `request.messages` expone cada mensaje con su componente y sus acciones, y cada acción de un sondeo lleva `metadata.type === 'poll'`.

- **419 o 401:** se cancela el aviso por omisión de Livewire (un `confirm` en inglés), se detiene el sondeo y se muestra, en español, "Tu sesión expiró..." con un enlace para entrar de nuevo.
- **Fallo de un sondeo** (500, 403, red): se silencia el modal con el HTML del error y se muestra "No pudimos actualizar la lista...", que se oculta con el siguiente sondeo exitoso.
- El script quedó en la vista (no en un archivo): un archivo requería registrar un asset de Filament (`filament:assets`) o cargarlo con `@assets`, y no se pudo comprobar en un navegador que Livewire cargue bien un módulo así.

### Accesibilidad (lo que se hizo)

- Las pestañas son un `role="group"` con botones `aria-pressed`: el componente `x-filament::tabs` emite un `<nav>` con `aria-current`, no un `tablist`, así que no se usó.
- Objetivos de 44 px en botones, pestañas, fecha y teléfono. Cada botón de fila lleva `aria-label` con el nombre de la persona. El estado nunca va solo en color (cada chip trae texto; la cancelada además va tachada).
- Contraste: solo tokens `--mb-*` del tema, claro y oscuro.

### Pruebas

- `ReservasPaginaTest` (37): acceso por rol y plan, navegación e insignia, pestañas, zona horaria (`Pacific/Auckland` frente a UTC), tope de 200, cada acción, errores de validación en español, manipulación de argumentos (reserva de otro restaurante, id inventado, estado inventado o ausente, hora y fecha inválidas, rol sin módulo, plan que baja a Solo menú con la página abierta), conflictos, sondeo y script con nonce.
- `ReservaEventosTest` (16): un evento por acción con quién, qué y origen; solo lo que cambió; sin datos del comensal; conflicto sin evento; transacción; origen desconocido; sin efecto en la caché del menú; retención, lotes y programación.
- `ReservasHtmlTest` (7): lee el HTML pintado (no solo las acciones de Livewire): sin directivas Blade sin compilar, `wire:click="mountAction('...', {...})"` con JSON válido en cada botón (incluso con un nombre con comillas, apóstrofo y etiquetas), esa misma expresión ejecutada por Livewire, y un recorrido de todas las vistas que falla si queda una directiva dentro de un atributo `wire:*`. Nació de un fallo real: `@js()` dentro de `<x-filament::button wire:click>` no se compila y llegaba literal al navegador; se usa `{{ Js::encode($argumentos) }}`.
- Siguen intactas las del servicio y la API (`GestionDeReservasTest`, `ReservasDelDiaTest`, `PanelApiReservasTest`, `PanelPlanAccesoTest`, `RolesOfrecidosTest`).
- Todas con `Carbon::setTestNow`, sin ids escritos a mano, valores que caben en sus columnas (MariaDB estricto) y sin `intl` ni `gd`.

### Pendientes

- **Pedidos** sigue pendiente (17.5); `/panel` sigue vivo.
- Navegación (PR 3): apuntar a esta página el ítem "Pedidos y reservas de hoy", el acceso `hoy` de `BarraInferiorDelPanel` y el enlace de `ProgresoDeConfiguracion`, y renombrar la página de ajustes. Mientras tanto conviven "Reservas" y "Pedidos y reservas" en el mismo grupo.
- Distinguir `panel` de `api` en el origen del historial (ver arriba).
- Eliminar reservas desde la página (fuera de este PR; el servicio y el historial ya lo soportan).
- Ajustes posibles tras probar en un navegador: pausar el sondeo por completo en pestañas ocultas y revisar el orden de foco en las ventanas.

### Qué probar a mano (390 px y escritorio, claro y oscuro)

1. Recibir una reserva del sitio público y confirmarla, moverla de hora y de fecha, marcar "Llegó", cancelarla y restaurarla.
2. Dos pestañas abiertas: cambiar una reserva en una y ver la otra actualizarse sola en unos 30 s; provocar un conflicto y ver el aviso (y, en Mover, que la ventana sigue abierta).
3. Con una ventana abierta (agregar), dejar pasar un sondeo y comprobar que lo escrito no se borra.
4. Teclado: abrir y cerrar cada ventana y comprobar que **el foco vuelve al botón que la abrió**; orden de tabulación; Esc para cerrar.
5. Lector de pantalla: que un sondeo sin cambios no diga nada y que uno con reservas nuevas diga "Hay N reservas nuevas por confirmar".
6. Cada plan (Completo, Sitio y menú, Solo menú), propietario y superadmin (con el banner), y la insignia de la navegación.
7. Sesión caducada a mitad de turno (borrar la cookie de sesión o esperar su vida de 120 min) y red cortada (modo avión): ver los avisos en español y que no aparece el modal con el HTML del error.
8. Que la purga programada aparezca en `php artisan schedule:list`.

### Qué no se pudo verificar

- Nada visual, de teclado ni de foco: no hay navegador. Que las ventanas de Filament devuelvan el foco al botón que las abrió **no se verificó**.
- MariaDB real: las pruebas usan SQLite en memoria (`lockForUpdate` no hace nada, el formato de `hora` y `fecha` puede variar, y el respaldo y la restauración con `mysqldump` no se ejecutaron).
- Cómo responde Filament a una petición de Livewire con la sesión caducada: si es una respuesta 401 o 419 se cubre; si fuera una redirección al inicio de sesión con HTML, el comportamiento de Livewire no se comprobó.
- El costo real del sondeo en una tablet o con 200 reservas, y peticiones realmente simultáneas.
- `search-docs` (laravel-boost) no conectó; el análisis se hizo leyendo el código instalado de Livewire 4.4.8 y Filament 5.10.2.
