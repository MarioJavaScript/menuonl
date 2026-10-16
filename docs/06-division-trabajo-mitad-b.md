# División de Trabajo y Contrato de Integración — Mitad B (Frente Público, QR y WhatsApp)

Este documento define la frontera exacta entre **Mitad A** y **Mitad B** del proyecto **MENUONL v1.0** para que ambos desarrolladores trabajen en paralelo **sin conflictos de Git**.

---

## 1. Resumen de la División entre Compañeros

| Bloque | Responsable | Alcance | Carpetas / Archivos Exclusivos |
| --- | --- | --- | --- |
| **Base Compartida + Mitad A** | Compañero A (`@MarioJavaScript`) | - Las 14 tablas de [`docs/esquema.sql`](esquema.sql) (migraciones, modelos Eloquent, relaciones, scopes, factories y seeders).<br>- Panel administrativo completo en Filament (`/admin`).<br>- Roles (`Superadmin`, `Propietario`, `Editor`) y Policies.<br>- Invitaciones por correo (caducidad 7 días).<br>- Conversión segura de imágenes a WebP.<br>- Bitácora de auditoría (`historial_cambios`) y disparo de purga de caché. | `database/migrations/*`<br>`database/seeders/*`<br>`database/factories/*`<br>`app/Models/*`<br>`app/Policies/*`<br>`app/Filament/*`<br>`app/Observers/*`<br>`app/Services/WebpImageService.php`<br>`app/Services/MenuCacheService.php` |
| **Mitad B (Este Documento)** | Compañero B (`@Elproshaitron-stack`) | - Menú público SSR `/m/{slug}` (y `?mesa=N`) en Blade + Tailwind CSS.<br>- Detección de idioma del navegador con fallback al `idioma_default` y selector de idioma.<br>- Visualización de horario y estado dinámico **Abierto / Cerrado**.<br>- Carrito de compras en el navegador y armado de enlace de pedido por WhatsApp (`wa.me`).<br>- Redirección corta `/q/{codigo}` → `/m/{slug}?mesa=N`.<br>- Generación y descarga de Códigos QR (PNG, SVG y hoja PDF imprimible).<br>- Lectura desde Caché L1 (`MenuCacheService`) y cabeceras HTTP para Cloudflare CDN (L2). | `app/Http/Controllers/MenuPublicoController.php`<br>`app/Http/Controllers/QrRedirectController.php`<br>`app/Http/Controllers/QrDownloadController.php`<br>`app/Services/QrGeneratorService.php`<br>`resources/views/menu/*`<br>`resources/views/qr/*`<br>`resources/js/*` / `resources/css/*`<br>`tests/Feature/MenuPublicoTest.php`<br>`tests/Feature/QrRedirectTest.php` |

> [!IMPORTANT]
> **Regla de oro para evitar conflictos en Git:**
> - **Mitad B** NO necesita crear ni modificar migraciones en `database/migrations/` (todas las 14 tablas del esquema v1.0 quedan listas en la Base Compartida).
> - En `routes/web.php`, **Mitad A** únicamente registra rutas de invitaciones (`/invitaciones/...`), mientras que **Mitad B** es dueña de las rutas `/m/{slug}` y `/q/{codigo}`.

---

## 2. Modelos Eloquent Disponibles (Contrato de Base Compartida)

Todos los modelos viven en `App\Models\` y reflejan exactamente el esquema [`docs/esquema.sql`](esquema.sql).

### Nota importante sobre `RestauranteScope` en rutas públicas (`/m/{slug}` y `/q/{codigo}`)
Los modelos dependientes (`Categoria`, `Producto`, `Contacto`, `Horario`, `CodigoQr`, `RestauranteIdioma`) usan el trait `BelongsToRestaurante`.
- Si `RestauranteScope::getRestauranteId()` es `null` (por defecto en peticiones públicas), o si en el controlador público prefieres fijar el contexto del restaurante tras resolver el `slug`:
  ```php
  use App\Models\Restaurante;
  use App\Models\Scopes\RestauranteScope;

  $restaurante = Restaurante::where('slug', $slug)->firstOrFail();
  RestauranteScope::setRestauranteId($restaurante->id);
  ```
- Si el restaurante tiene `estado === 'suspendido'` (o `! $restaurante->isActivo()`), se debe renderizar la vista de **"Menú no disponible"** (HTTP 404/410 o vista dedicada según `RF-30` y diagrama `6.1`).

### Tabla de Modelos y Relaciones Clave para Mitad B

| Modelo (`App\Models\*`) | Tabla | Relaciones y Atributos Clave para el Menú Público |
| --- | --- | --- |
| `Restaurante` | `restaurantes` | - Atributos: `nombre`, `slug`, `descripcion`, `logo`, `portada`, `moneda`, `idioma_default`, `zona_horaria`, `whatsapp_pedidos`, `pedidos_activos` (`bool`), `mostrar_agotados` (`bool`), `estado` (`activo` \| `suspendido`).<br>- Relaciones: `idiomas()` (`RestauranteIdioma`), `categorias()`, `productos()`, `contactos()`, `horarios()`, `codigosQr()`.<br>- Métodos helper: `isActivo(): bool`, `estaAbiertoAhora(?Carbon $now = null): bool`. |
| `RestauranteIdioma` | `restaurante_idiomas` | - Atributos: `restaurante_id`, `idioma` (ej. `es`, `en`, `pt-BR`), `orden`. |
| `Categoria` | `categorias` | - Atributos: `id`, `restaurante_id`, `orden`, `visible` (`bool`), `deleted_at`.<br>- Relaciones: `traducciones()` (`CategoriaTraduccion`), `productos()`.<br>- Helper: `traduccion(?string $idioma = null): ?CategoriaTraduccion` (con fallback automático a `idioma_default`). |
| `CategoriaTraduccion` | `categoria_traducciones` | - Atributos: `categoria_id`, `idioma`, `nombre`, `descripcion`. |
| `Producto` | `productos` | - Atributos: `id`, `restaurante_id`, `categoria_id`, `precio` (`decimal:2`), `precio_oferta` (`decimal:2` nullable), `imagen`, `disponible` (`bool`), `visible` (`bool`), `destacado` (`bool`), `orden`, `deleted_at`.<br>- Relaciones: `traducciones()` (`ProductoTraduccion`), `variantes()` (`ProductoVariante`), `etiquetas()` (`Etiqueta`).<br>- Helper: `traduccion(?string $idioma = null): ?ProductoTraduccion`. |
| `ProductoVariante` | `producto_variantes` | - Atributos: `id`, `producto_id`, `precio` (`decimal:2`), `disponible` (`bool`), `orden`.<br>- Relaciones: `traducciones()` (`VarianteTraduccion`).<br>- Helper: `traduccion(?string $idioma = null): ?VarianteTraduccion`. |
| `Etiqueta` | `etiquetas` | - Atributos: `id`, `codigo` (`vegano`, `vegetariano`, `picante`, `sin_gluten`, `sin_lactosa`, `nuevo`, `recomendado`), `icono`. |
| `Contacto` | `contactos` | - Atributos: `tipo` (`telefono`, `whatsapp`, `email`, `direccion`, `instagram`, `facebook`, `tiktok`, `web`, `otro`), `etiqueta`, `valor`, `orden`, `visible` (`bool`). |
| `Horario` | `horarios` | - Atributos: `dia_semana` (`0`=Domingo .. `6`=Sábado), `abre` (`H:i:s`), `cierra` (`H:i:s`), `cerrado` (`bool`). Soporta múltiples turnos por día y turnos nocturnos (`cierra < abre`). |
| `CodigoQr` | `codigos_qr` | - Atributos: `id`, `restaurante_id`, `nombre`, `mesa` (nullable), `codigo` (único, hasta 16 chars), `activo` (`bool`). |

---

## 3. Contrato de Caché (`App\Services\MenuCacheService`)

La **Mitad A** invalida automáticamente la caché cada vez que un propietario o editor modifica platillos, precios, categorías, contactos, horarios o configuración del restaurante.

- **Formato de clave L1 (Redis / Cache Store de Laravel):**
  ```
  menu_publico:{restaurante_id}:{idioma}
  ```
- **Uso desde el controlador de Mitad B (`MenuPublicoController`):**
  - Puedes usar `MenuCacheService::rememberMenuPayload(Restaurante $restaurante, string $idioma, Closure $callback)` (o `Cache::remember("menu_publico:{$restaurante->id}:{$idioma}", 3600, ...)`).
  - Cuando el panel guarda cualquier cambio, `MenuCacheService::invalidarRestaurante($restaurante)` limpia todos los idiomas activos de ese restaurante y despacha el job de purga para Cloudflare (`PurgarCacheRestauranteJob`).

---

## 4. Checklist de Entregables para el Compañero de la Mitad B

### B1. Redirección Corta de Códigos QR (`/q/{codigo}`) — RF-01, RF-20
- [ ] Ruta `GET /q/{codigo}` (`name('qr.redirect')`).
- [ ] Validar `codigo` con expresión regular alfanumérica (`^[A-Za-z0-9_-]{1,16}$`).
- [ ] Buscar en `CodigoQr::withoutGlobalScopes()->where('codigo', $codigo)->where('activo', true)->with('restaurante')->first()`.
- [ ] Si no existe, está inactivo, o su restaurante no está `activo`, mostrar la vista **"Menú no disponible"**.
- [ ] Si es válido:
  - Si `mesa` tiene valor, redirigir (302) a `route('menu.publico', ['slug' => $restaurante->slug, 'mesa' => $qr->mesa])`.
  - Si `mesa` es `null`, redirigir (302) a `route('menu.publico', ['slug' => $restaurante->slug])`.

### B2. Menú Público SSR (`/m/{slug}`) — RF-01 a RF-06, RNF-01, RNF-08
- [ ] Ruta `GET /m/{slug}` (`name('menu.publico')`).
- [ ] Resolver el idioma activo:
  1. Parámetro query `?lang=xx` si está en `restaurante_idiomas`.
  2. Idioma del header `Accept-Language` del navegador si está en `restaurante_idiomas`.
  3. Fallback a `$restaurante->idioma_default`.
- [ ] Mostrar cabecera del restaurante: `nombre`, `descripcion`, `logo`, `portada`, selector de idiomas activos y badge dinámico **Abierto / Cerrado** calculado con `$restaurante->estaAbiertoAhora()` en la `$restaurante->zona_horaria`.
- [ ] Mostrar sección de "Recomendados" (`productos` con `destacado = true` y `visible = true`) y listado de `categorias` visibles ordenadas por `orden`.
- [ ] Para cada platillo (`visible = true`, ordenado por `orden`):
  - Si `disponible = false`: ocultarlo si `$restaurante->mostrar_agotados === false`, o mostrarlo con insignia **"Agotado"** y botón de agregar deshabilitado si `mostrar_agotados === true`.
  - Si tiene `variantes` disponibles, mostrar cada variante con su propio precio (`RF-03`).
  - Si no tiene variantes y tiene `precio_oferta` menor a `precio`, mostrar `precio` tachado y resaltar `precio_oferta` (`RF-02`).
  - Mostrar `etiquetas` dietéticas (vegano, picante, sin gluten, etc.) e imagen WebP con `loading="lazy"`.
- [ ] Sección de Contactos (`visible = true`, ordenados por `orden`) y tabla de Horarios por día:
  - Enlaces seguros (`tel:`, `https://wa.me/`, `mailto:`, `https://maps.google.com/...` o URL validada).
  - Usar siempre `{{ }}` en Blade (nunca `{!! !!}`) para prevenir XSS (`docs/02-seguridad.md`).
- [ ] Diseño móvil accesible (contraste AA, botones táctiles de mínimo `44x44 px`).

### B3. Carrito en Navegador y Pedido por WhatsApp — RF-07
- [ ] Habilitar el carrito únicamente cuando `$restaurante->pedidos_activos === true` y el restaurante esté abierto (`$restaurante->estaAbiertoAhora()`).
- [ ] Persistir el carrito en `localStorage` bajo una clave aislada por restaurante (`menuonl_cart_{slug}`), almacenando:
  - Platillo, variante seleccionada (si aplica), precio unitario vigente, cantidad y nota opcional por platillo.
  - Número de mesa (`?mesa=N` prellenado desde la URL si viene de un QR de mesa, o editable por el comensal).
- [ ] Generar el deep-link `https://wa.me/{whatsapp_pedidos}?text={urlencode($mensaje)}` con el desglose legible de platillos, variantes, cantidades, notas, mesa y **Total** formateado en `$restaurante->moneda`.

### B4. Descarga de Códigos QR Imprimibles (PNG, SVG, PDF) — RF-20
- [ ] Servicio o controlador autenticado (`/admin/codigos-qr/{codigoQr}/descargar/{formato}`) para generar el QR apuntando a `url("/q/{$codigoQr->codigo}")` en formatos `svg`, `png` y hoja imprimible (`pdf` o vista HTML lista para imprimir con logo, nombre del restaurante y número de mesa).

### B5. Pruebas Feature en PHPUnit (`tests/Feature/MenuPublicoTest.php` y `QrRedirectTest.php`)
- [ ] Verificar que `/q/{codigo}` redirige a `/m/{slug}?mesa=N` cuando está activo y devuelve página de no disponible cuando el QR o el restaurante están suspendidos.
- [ ] Verificar que `/m/{slug}` muestra categorías/platillos ordenados, aplica fallback al `idioma_default`, respeta `mostrar_agotados` y escapa cualquier intento de XSS.
