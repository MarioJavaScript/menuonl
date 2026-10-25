# División de Trabajo y Contrato de Integración — Mitad B (Frente Público, QR y WhatsApp)

Este documento define la frontera exacta entre **Mitad A** y **Mitad B** del proyecto **Menubly v1.0** para que ambos desarrolladores trabajen en paralelo **sin conflictos de Git**.

---

## 1. Resumen de la División entre Compañeros

| Bloque | Responsable | Alcance | Carpetas / Archivos Exclusivos |
| --- | --- | --- | --- |
| **Base Compartida + Mitad A (Completada en `main`)** | Compañero A (`@MarioJavaScript`) | - Las 14 tablas de [`docs/esquema.sql`](esquema.sql) (migraciones, modelos Eloquent, relaciones, scopes, factories y seeders).<br>- Panel administrativo completo en Filament 5 (`/admin`).<br>- Roles (`Superadmin`, `Propietario`, `Editor`), `MatrizPolicy` y 2FA (`ExigeDosFactores`).<br>- Invitaciones por correo (caducidad 7 días, token SHA-256).<br>- Conversión segura de imágenes a WebP (`WebpImageService`).<br>- Bitácora de auditoría (`historial_cambios` / trait `Auditable`) y disparo de purga de caché (`MenuCacheService`). | `database/migrations/*`<br>`database/seeders/*`<br>`database/factories/*`<br>`app/Models/*`<br>`app/Policies/*`<br>`app/Filament/*`<br>`app/Observers/*`<br>`app/Services/WebpImageService.php`<br>`app/Services/MenuCacheService.php`<br>`app/Services/InvitacionService.php` |
| **Mitad B (Completada y Verificada en `main`)** | Compañero B (`@Elproshaitron-stack`) | - Menú público SSR `/{slug}/menu` (y `?mesa=N`) en Blade + Tailwind CSS.<br>- Detección de idioma del navegador con fallback al `idioma_default` y selector de idioma.<br>- Visualización de horario y estado dinámico **Abierto / Cerrado**.<br>- Carrito de compras en el navegador y armado de enlace de pedido por WhatsApp (`wa.me`).<br>- Redirección corta `/q/{codigo}` → `/{slug}/menu?mesa=N`.<br>- Generación y descarga de Códigos QR (PNG, SVG y hoja PDF/HTML imprimible).<br>- Lectura desde Caché L1 (`MenuCacheService`) y cabeceras HTTP para Cloudflare CDN (L2). | `app/Http/Controllers/MenuPublicoController.php`<br>`app/Http/Controllers/QrRedirectController.php`<br>`app/Http/Controllers/QrDownloadController.php`<br>`app/Services/QrGeneratorService.php`<br>`resources/views/menu/*`<br>`resources/views/qr/*`<br>`resources/js/*` / `resources/css/*`<br>`tests/Feature/MenuPublicoTest.php`<br>`tests/Feature/QrRedirectTest.php`<br>`tests/Feature/QrDownloadTest.php` |

> [!IMPORTANT]
> **Regla de oro para evitar conflictos en Git:**
> - **Mitad B** NO necesita crear ni modificar migraciones en `database/migrations/` (todas las 14 tablas del esquema v1.0 ya están listas en `main`).
> - En `routes/web.php`, **Mitad A** únicamente registra las rutas `/invitaciones/{token}`, mientras que **Mitad B** es dueña de las rutas `/{slug}/menu`, `/q/{codigo}` y descarga de QR.

---

## 2. Modelos Eloquent Disponibles (Contrato de Base Compartida)

Todos los modelos viven en `App\Models\` y reflejan exactamente el esquema [`docs/esquema.sql`](esquema.sql).

### Nota importante sobre `RestauranteScope` en rutas públicas (`/{slug}/menu` y `/q/{codigo}`)
Los modelos dependientes (`Categoria`, `Producto`, `Contacto`, `Horario`, `CodigoQr`, `RestauranteIdioma`) usan el trait `BelongsToRestaurante`.
- En peticiones públicas fuera de Filament (`Filament::isServing() === false`), `RestauranteScope::resolveRestauranteId()` es `null` a menos que fijes el contexto explícitamente:
  ```php
  use App\Models\Restaurante;
  use App\Models\Scopes\RestauranteScope;

  $restaurante = Restaurante::where('slug', $slug)->first();
  if (! $restaurante || ! $restaurante->isActivo()) {
      return response()->view('menu.no-disponible', [], 404);
  }
  RestauranteScope::setRestauranteId($restaurante->id);
  ```

### Tabla de Modelos, Scopes y Helpers Listos para Usar en Mitad B

| Modelo (`App\Models\*`) | Tabla | Relaciones, Scopes y Helpers Implementados |
| --- | --- | --- |
| `Restaurante` | `restaurantes` | - Atributos: `nombre`, `slug`, `descripcion`, `logo`, `portada`, `moneda`, `idioma_default`, `zona_horaria`, `whatsapp_pedidos`, `pedidos_activos` (`bool`), `mostrar_agotados` (`bool`), `estado` (`activo` \| `suspendido`).<br>- Relaciones: `idiomas()`, `categorias()`, `productos()`, `contactos()`, `horarios()`, `codigosQr()`.<br>- Helpers: `$restaurante->isActivo(): bool`, `$restaurante->estaAbiertoAhora(?Carbon $now = null): bool`. |
| `RestauranteIdioma` | `restaurante_idiomas` | - Atributos: `restaurante_id`, `idioma` (ej. `es`, `en`, `pt-BR`), `orden`.<br>- Scope: `RestauranteIdioma::ordenado()`. |
| `Categoria` | `categorias` | - Atributos: `id`, `restaurante_id`, `orden`, `visible` (`bool`), `deleted_at`.<br>- Relaciones: `traducciones()`, `productos()`.<br>- Scopes: `Categoria::visible()->ordenado()`.<br>- Helper con fallback a `idioma_default`: `$categoria->traduccion(?string $idioma = null): ?CategoriaTraduccion`. |
| `CategoriaTraduccion` | `categoria_traducciones` | - Atributos: `categoria_id`, `idioma`, `nombre`, `descripcion`. |
| `Producto` | `productos` | - Atributos: `id`, `restaurante_id`, `categoria_id`, `precio` (`decimal:2`), `precio_oferta` (`decimal:2` nullable), `imagen`, `disponible` (`bool`), `visible` (`bool`), `destacado` (`bool`), `orden`, `deleted_at`.<br>- Relaciones: `traducciones()`, `variantes()`, `etiquetas()`.<br>- Scopes: `Producto::visible()->disponible()->destacado()->ordenado()`.<br>- Helpers: `$producto->traduccion(?string $idioma = null): ?ProductoTraduccion`, `$producto->precioVigente(): string`, `$producto->tieneVariantes(): bool`. |
| `ProductoVariante` | `producto_variantes` | - Atributos: `id`, `producto_id`, `precio` (`decimal:2`), `disponible` (`bool`), `orden`.<br>- Relaciones: `traducciones()`.<br>- Scopes: `ProductoVariante::disponible()->ordenado()`.<br>- Helper: `$variante->traduccion(?string $idioma = null): ?VarianteTraduccion`. |
| `Etiqueta` | `etiquetas` | - Atributos: `id`, `codigo` (`vegano`, `vegetariano`, `picante`, `sin_gluten`, `sin_lactosa`, `nuevo`, `recomendado`), `icono`. |
| `Contacto` | `contactos` | - Atributos: `tipo` (`telefono`, `whatsapp`, `email`, `direccion`, `instagram`, `facebook`, `tiktok`, `web`, `otro`), `etiqueta`, `valor`, `orden`, `visible` (`bool`).<br>- Scopes: `Contacto::visible()->ordenado()`. |
| `Horario` | `horarios` | - Atributos: `dia_semana` (`0`=Domingo .. `6`=Sábado), `abre` (`H:i:s`), `cierra` (`H:i:s`), `cerrado` (`bool`). Soporta múltiples turnos por día y turnos nocturnos (`cierra <= abre`). |
| `CodigoQr` | `codigos_qr` | - Atributos: `id`, `restaurante_id`, `nombre`, `mesa` (nullable), `codigo` (único, hasta 16 chars), `activo` (`bool`).<br>- Scope y helper: `CodigoQr::activo()`, `$qr->esMesa(): bool`. |

---

## 3. Contrato de Caché (`App\Services\MenuCacheService`)

La **Mitad A** invalida automáticamente la caché cada vez que un propietario o editor modifica platillos, precios, categorías, contactos, horarios o configuración del restaurante (mediante el trait `Auditable` y `AuditableObserver`).

- **Formato de clave L1 (Redis / Cache Store de Laravel):**
  ```
  menu:{restaurante_id}:{idioma}
  ```
- **Métodos disponibles en `App\Services\MenuCacheService`:**
  - `$cacheService->rememberMenu(Restaurante $restaurante, string $idioma, Closure $callback, int $ttl = 86400): array`
  - `$cacheService->getMenu(Restaurante $restaurante, string $idioma): ?array`
  - `$cacheService->putMenu(Restaurante $restaurante, string $idioma, array $data, int $ttl = 86400): void`
  - `$cacheService->invalidateRestaurante(Restaurante $restaurante): void` (limpia las claves L1 de todos los idiomas del restaurante y despacha `PurgarCacheRestauranteJob` hacia Cloudflare).

---

## 4. Checklist de Entregables para Completar la Mitad B

### B1. Redirección Corta de Códigos QR (`/q/{codigo}`) — RF-01, RF-20
- [x] Ruta `GET /q/{codigo}` (`name('qr.redirect')`).
- [x] Validar `codigo` con `App\Rules\CodigoQrValido` (`^[A-Za-z0-9_-]{1,16}$`).
- [x] Buscar en `CodigoQr::withoutGlobalScopes()->where('codigo', $codigo)->where('activo', true)->with('restaurante')->first()`.
- [x] Si no existe, está inactivo, o su restaurante no está `activo` (`! $restaurante->isActivo()`), mostrar la vista **"Menú no disponible"** (HTTP 404).
- [x] Si es válido:
  - Si `$qr->esMesa()`, redirigir (302) a `route('menu.publico', ['slug' => $restaurante->slug, 'mesa' => $qr->mesa])`.
  - Si no tiene mesa, redirigir (302) a `route('menu.publico', ['slug' => $restaurante->slug])`.

### B2. Menú Público SSR (`/{slug}/menu`) — RF-01 a RF-06, RNF-01, RNF-08
- [x] Ruta `GET /{slug}/menu` (`name('menu.publico')`).
- [x] Resolver el idioma activo:
  1. Parámetro query `?lang=xx` si está en `restaurante_idiomas`.
  2. Idioma del header `Accept-Language` del navegador si está en `restaurante_idiomas`.
  3. Fallback a `$restaurante->idioma_default`.
- [x] Mostrar cabecera del restaurante: `nombre`, `descripcion`, `logo`, `portada`, selector de idiomas activos y badge dinámico **Abierto / Cerrado** calculado con `$restaurante->estaAbiertoAhora()`.
- [x] Mostrar sección de "Recomendados" (`Producto::visible()->destacado()->ordenado()`) y listado de `Categoria::visible()->ordenado()`.
- [x] Para cada platillo (`visible = true`, ordenado por `orden`):
  - Si `disponible = false`: ocultarlo si `$restaurante->mostrar_agotados === false`, o mostrarlo con insignia **"Agotado"** y botón de agregar deshabilitado si `mostrar_agotados === true`.
  - Si `$producto->tieneVariantes()`, mostrar cada variante disponible con su propio precio (`RF-03`).
  - Si no tiene variantes y tiene `precio_oferta` menor a `precio`, mostrar `precio` tachado y resaltar `precio_oferta` (`$producto->precioVigente()`, `RF-02`).
  - Mostrar `etiquetas` dietéticas (vegano, picante, sin gluten, etc.) e imagen WebP con `loading="lazy"`.
- [x] Sección de Contactos (`Contacto::visible()->ordenado()`) y tabla de Horarios por día:
  - Enlaces seguros (`tel:`, `https://wa.me/`, `mailto:`, `https://maps.google.com/...` o URL validada).
  - Usar siempre `{{ }}` en Blade (nunca `{!! !!}`) para prevenir XSS (`docs/02-seguridad.md`).
- [x] Diseño móvil accesible (contraste AA, botones táctiles de mínimo `44x44 px`).

### B3. Carrito en Navegador y Pedido por WhatsApp — RF-07
- [x] Habilitar el carrito únicamente cuando `$restaurante->pedidos_activos === true` y el restaurante esté abierto (`$restaurante->estaAbiertoAhora()`).
- [x] Persistir el carrito en `localStorage` bajo una clave aislada por restaurante (`menuonl_cart_{slug}`), almacenando:
  - Platillo, variante seleccionada (si aplica), precio unitario vigente, cantidad y nota opcional por platillo.
  - Número de mesa (`?mesa=N` prellenado desde la URL si viene de un QR de mesa, o editable por el comensal).
- [x] Generar el deep-link `https://wa.me/{whatsapp_pedidos}?text={urlencode($mensaje)}` con el desglose legible de platillos, variantes, cantidades, notas, mesa y **Total** formateado en `$restaurante->moneda`.

### B4. Descarga de Códigos QR Imprimibles (PNG, SVG, PDF) — RF-20
- [x] Servicio y controlador autenticado para generar el QR apuntando a `url("/q/{$codigoQr->codigo}")` en formatos `svg`, `png` y hoja imprimible (`pdf` / vista de impresión con logo, nombre del restaurante y número de mesa), más acciones de descarga en `CodigosQrTable`.

### B5. Pruebas Feature en PHPUnit (`tests/Feature/MenuPublicoTest.php`, `QrRedirectTest.php`, `QrDownloadTest.php`)
- [x] Verificar que `/q/{codigo}` redirige a `/{slug}/menu?mesa=N` cuando está activo y devuelve página de no disponible cuando el QR o el restaurante están suspendidos.
- [x] Verificar que `/{slug}/menu` muestra categorías/platillos ordenados, aplica fallback al `idioma_default`, respeta `mostrar_agotados`, usa `MenuCacheService` y escapa cualquier intento de XSS.
