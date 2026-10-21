# MENUONL

Menú digital con QR (y NFC) para restaurantes, multi-restaurante.

El comensal escanea un QR, ve el menú al instante sin instalar ninguna app y, si el restaurante lo tiene habilitado, arma un carrito y envía su pedido por WhatsApp. Cada restaurante administra sus platillos, precios, variantes, fotos, contactos y horarios desde un panel web responsivo.

**Estado actual (19 de octubre de 2026) — rama `rediseno-simplicidad`:**
- ✅ **Panel de servicio `/panel` (Fases 1–4):** vista Blade + Vite que consume la API `/api/v1/panel` con la sesión de login. Reservas, pedidos, menú, resumen del día, roles operativos (`gerente`, `recepcion`, `cocina`) con gestión de equipo, ajustes funcionales del restaurante y subida de fotos con recorte, WebP y tamaños responsive (galería de imágenes).
- ✅ **Sitios públicos `/{slug}` (Fase 5):** sitio dinámico del restaurante con tres plantillas en Blade.
- 🚧 **Reservas públicas:** formulario de reserva en el sitio conectado a `ReservaPublicaController` / `ReservasPublicas` (disponibilidad por horario y aforo, validación de datos de contacto, rate limiting, origen de la reserva). Aún hay pruebas pendientes de ajustar.
- ✅ **API REST pública:** menú público vía `/api/v1/menu/{slug}`. El cliente Next.js experimental (`frontend/`) se retiró; sigue en el historial de git.
- Suite actual: **770 pruebas** (761 pasan, 8 fallan —reservas por fecha/hora y cookie de sesión—, 1 omitida).

**Historial (16 de octubre de 2026):**
- ✅ **Fase 0 y Base de Datos v1.0 completadas:** 14 tablas del esquema DDL migradas, modelos Eloquent con aislamiento multi-tenant (`BelongsToRestaurante` / `RestauranteScope`), factories y seeders (`EtiquetaSeeder`, `DemoRestauranteSeeder`).
- ✅ **Mitad A completada (Panel Admin `/admin`, Seguridad y Servicios de Dominio):** CRUDs completos en Filament 5 (Categorías, Productos con edición rápida de precios/disponibilidad, Variantes, Traducciones multi-idioma, Etiquetas, Contactos, Horarios, Códigos QR, Restaurantes, Usuarios, Invitaciones e Historial de Cambios), Roles y Policies (`Superadmin`, `Propietario`, `Editor`), 2FA obligatorio para Propietario y Superadmin, rate-limiting de login, conversión segura de imágenes a WebP (`WebpImageService`), protección de archivos entre tenants, auditoría forense (`Auditable` / `AuditableObserver`) e invalidación de caché L1/L2 (`MenuCacheService` y `PurgarCacheRestauranteJob`).
- ✅ **Mitad B completada (Frente Público Comensal, Carrito WhatsApp y Descarga QR):** Menú público SSR `/{slug}/menu` con Blade + Tailwind CSS v4, multi-idioma con autodetección (`?lang=`, `Accept-Language`, default), selector de variantes con precios propios, etiquetas dietéticas, cálculo dinámico de apertura/cierre en tiempo real según la zona horaria del restaurante, carrito en cliente persistido en `localStorage` con pedido estructurado a WhatsApp (`wa.me`), redirección corta `/q/{codigo}` con soporte de mesas, generador nativo de códigos QR en PHP 8.3 (SVG y PNG rasterizado con GD) y ficha imprimible PDF, y generación de mesas por lote en Filament 5. Todo cubierto con una suite de 476 pruebas automatizadas (475 pasadas, 0 fallos, 0 errores).

---

## Stack Tecnológico

| Componente | Tecnología | Propósito |
| --- | --- | --- |
| **Backend & Framework** | Laravel 13 (PHP 8.3+ con OPcache) | Lógica de negocio, aislamiento multi-tenant y renderizado SSR |
| **Menú público** | Blade + Tailwind CSS v4 (Vite) | Páginas ultraligeras, accesibles, en una sola petición HTML |
| **Panel de administración** | Filament 5 | CRUD administrativo multi-tenant, ordenamiento drag-and-drop, edición rápida y 2FA |
| **Base de datos** | MariaDB 10.6+ (InnoDB, utf8mb4) / SQLite en tests | Persistencia relacional, integridad referencial y borrado lógico |
| **Caché y sesiones** | Redis 6+ / 7+ | Caché L1 en VPS (menús armados) y almacén de sesiones rápidas |
| **Colas de trabajo** | Supervisor + Laravel Queues | Procesamiento asíncrono (purga CDN en Cloudflare, envío de correos) |
| **Servidor web** | Nginx | Terminación TLS, compresión HTTP y entrega estática |
| **CDN / DNS / WAF** | Cloudflare (Edge Network) | Caché L2 en servidores de borde (LATAM), protección DDoS y SSL |

---

## Especificaciones Generales

### Actores del Sistema

| Actor | Responsabilidad y Permisos |
| --- | --- |
| **Comensal** | Escanea el QR, consulta el menú público y envía su pedido por WhatsApp. No requiere registro ni aplicación instalada. |
| **Propietario** | Acceso total a su restaurante: menú, precios, contactos, configuración, horarios y gestión de usuarios (invitaciones). Requiere 2FA activo en producción. |
| **Editor** | Personal operativo del restaurante. Modifica menú, precios, contactos, horarios y códigos QR. Sin acceso a usuarios, invitaciones ni configuración general. |
| **Superadmin** | Operador global de MENUONL. Da de alta, suspende y reactiva restaurantes, y consulta usuarios de la plataforma. Requiere 2FA activo en producción. |

### Funcionalidades Principales

- **Menú público (`/{slug}/menu`):** categorías, platillos, variantes con precio propio, ofertas (precio tachado), etiquetas dietéticas, fotos WebP con *lazy-loading*, multi-idioma con fallback, información de contacto, horarios y estado dinámico abierto/cerrado.
- **Pedido por WhatsApp:** carrito en navegador local del comensal y deep-link prellenado con desglose de productos, número de mesa y total (sin pagos en línea ni persistencia en BD en v1.0).
- **Panel de administración (`/admin`):** CRUD y reordenamiento drag-and-drop con Filament 5, tabla rápida de edición de precios y disponibilidad, conversor de imágenes a WebP, captura de traducciones, invitaciones por email y log forense de auditoría.
- **Códigos QR:** generación para descarga en PNG, SVG o PDF imprimible (general o por mesa), direccionados a `/q/{codigo}` para desacoplar el material físico de posibles cambios de slug.
- **Multi-restaurante:** administración de múltiples sedes/restaurantes bajo una misma cuenta de usuario con invitaciones seguras por correo (tokens SHA-256 con caducidad de 7 días).

### Requisitos No Funcionales Clave

| Área | Requisito |
| --- | --- |
| **Rendimiento** | Menú público optimizado para móvil; carga completa en menos de **1.5 s** sobre 4G en Latinoamérica. HTML generado en servidor (SSR). |
| **Caché en 2 niveles** | Almacenamiento L1 en Redis (VPS) + L2 en Cloudflare CDN; purga coordinada en segundos al guardar cambios (`MenuCacheService` + `PurgarCacheRestauranteJob`). |
| **Aislamiento multi-tenant** | Filtrado obligatorio por `restaurante_id` en todas las consultas del panel mediante `RestauranteScope`, `BelongsToRestaurante` y `MatrizPolicy`. |
| **Seguridad** | HTTPS obligatorio, hashing Argon2id/Bcrypt, 2FA para propietarios y superadmins (`ExigeDosFactores`), validación de esquemas en contactos (`ValorContactoSeguro`), reprocesado obligatorio a WebP sin EXIF (`WebpImageService`), protección CSRF y rate-limiting en login e invitaciones. |
| **Consistencia monetaria** | Precios en `DECIMAL(10,2)` con restricciones no negativas en modelo y base de datos (nunca punto flotante). |
| **Integridad y respaldo** | Borrado lógico (`deleted_at`) en restaurantes, categorías y platillos; respaldos diarios automáticos retenidos por 14 días fuera del VPS. |

---

## Modelo de Datos

El esquema completo está definido en [`docs/esquema.sql`](docs/esquema.sql) y en `database/migrations/`:
- `users` (`usuarios`): Administradores y editores con soporte de 2FA, `es_superadmin`, `activo` y `ultimo_acceso`.
- `restaurantes`: Configuración central, slugs únicos, moneda, idioma por defecto, zona horaria y switch de pedidos WhatsApp.
- `restaurante_usuario`: Tabla pivote de asignación con roles `propietario` y `editor`.
- `invitaciones`: Tokens criptográficos hasheados (SHA-256) con caducidad de 7 días.
- `restaurante_idiomas`: Catálogo de idiomas habilitados por restaurante.
- `contactos`: Canales de comunicación (teléfono, WhatsApp, email, dirección, redes) con orden, visibilidad y validación de esquemas seguros.
- `horarios`: Horarios por día de la semana (`0=Domingo..6=Sábado`) con soporte para turnos partidos y turnos nocturnos (`cierra <= abre`).
- `categorias` y `categoria_traducciones`: Agrupación de platillos con traducciones desacopladas y borrado lógico.
- `productos` y `producto_traducciones`: Platillos, precios en `DECIMAL(10,2)`, precio de oferta, fotos WebP e insignias.
- `producto_variantes` y `variante_traducciones`: Tamaños/presentaciones con precio propio.
- `etiquetas` y `producto_etiqueta`: Catálogo global de 7 insignias (`vegano`, `vegetariano`, `picante`, `sin_gluten`, `sin_lactosa`, `nuevo`, `recomendado`).
- `codigos_qr`: URLs cortas `/q/{codigo}` desacopladas para preservar material impreso físico.
- `reservas`, `pedidos` y `galeria_imagenes`: Operación diaria del panel de servicio y fotos del sitio público.
- `historial_cambios`: Auditoría forense de cambios con snapshots JSON (`antes` / `después`).

---

## Instalación y Desarrollo Local

### 1. Clonar e instalar dependencias
```bash
git clone https://github.com/MarioJavaScript/menuonl.git
cd menuonl
composer run setup
```
*(O paso a paso)*:
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
```

### 2. Usuarios de prueba (creados por `DemoRestauranteSeeder`)
Al ejecutar `php artisan migrate:fresh --seed` en entorno `local`, se crea el restaurante de demostración **Taquería La Esquina** (`slug`: `la-esquina`) y las siguientes cuentas para `/admin` (contraseña `password`):
- **Superadmin:** `superadmin@menuonl.test`
- **Propietario:** `propietario@menuonl.test`
- **Editor:** `editor@menuonl.test`

*(En desarrollo local puedes definir `REQUIRE_2FA=false` en `.env` si deseas entrar al panel sin configurar la app autenticadora TOTP).*

### 3. Ejecutar servidor de desarrollo y pruebas
```bash
# Levantar servidor local + Vite + cola
composer run dev

# Ejecutar la suite completa de pruebas en PHPUnit (770 pruebas)
php artisan test --compact

# Formatear código con Laravel Pint
vendor/bin/pint
```

---

## Documentación del Repositorio

Todos los detalles técnicos y especificaciones funcionales se encuentran en la carpeta [`docs/`](docs/):

| Documento | Descripción |
| --- | --- |
| [docs/01-especificacion.md](docs/01-especificacion.md) | Especificación funcional completa v1.0, actores, diccionario de datos y diagramas de flujo Mermaid |
| [docs/02-seguridad.md](docs/02-seguridad.md) | Guía de hardening: sanitización de WebP, mitigación SQLi/XSS, CSRF, 2FA, aislamiento de tenants y rate limiting |
| [docs/03-nfc-y-ventas.md](docs/03-nfc-y-ventas.md) | Integración con chips NFC físicos NTAG213/215, modelos de tags y estrategia comercial de venta |
| [docs/04-roadmap-mvp.md](docs/04-roadmap-mvp.md) | Estado de avance por fases (Fase 0 a Fase 3) |
| [docs/05-pendientes.md](docs/05-pendientes.md) | Registro de decisiones técnicas, estado de pendientes y temas de infraestructura |
| [docs/06-division-trabajo-mitad-b.md](docs/06-division-trabajo-mitad-b.md) | División de arquitectura (Mitad A vs. Mitad B) y contratos de modelos/caché para el frente público |
| [docs/esquema.sql](docs/esquema.sql) | DDL MariaDB 10.6+ completo con llaves foráneas, índices compuestos y datos iniciales de etiquetas |
| [docs/especificacion.docx](docs/especificacion.docx) | Documento fuente de especificación funcional en formato Microsoft Word |

---

## Estructura del Repositorio

```
menuonl/
├── app/
│   ├── Filament/           # Panel administrativo multi-tenant (Resources, Schemas, Tables, Pages, Components)
│   ├── Http/               # Controladores (MenuPublicoController, QrRedirectController, QrDownloadController, Invitaciones) y Middleware (ExigeDosFactores)
│   ├── Jobs/               # PurgarCacheRestauranteJob (purga asíncrona L2 en Cloudflare)
│   ├── Mail/               # InvitacionMail
│   ├── Models/             # 14 Modelos Eloquent + HasTraducciones + RestauranteScope
│   ├── Observers/          # AuditableObserver (bitácora forense e invalidación de caché)
│   ├── Policies/           # MatrizPolicy y Policies por modelo (Superadmin / Propietario / Editor)
│   ├── Rules/              # CodigoQrValido, ValorContactoSeguro
│   ├── Services/           # QrGeneratorService, WebpImageService, MenuCacheService, InvitacionService, Catalogos
│   └── Traits/             # BelongsToRestaurante, Auditable, ProtegeImagenesDelRestaurante
├── config/
│   └── menuonl.php         # Configuración de 2FA, límites de login y límites de imágenes
├── database/
│   ├── factories/          # Factories para los modelos del dominio
│   ├── migrations/         # Esquema completo de 14 tablas + columnas 2FA
│   └── seeders/            # EtiquetaSeeder y DemoRestauranteSeeder
├── docs/                   # Especificación, seguridad, roadmap, DDL y contrato Mitad B
├── resources/views/        # Vistas de menú público (/{slug}/menu), ficha imprimible QR, invitaciones y correos
├── routes/                 # web.php (/panel, /{slug}, /{slug}/menu, /q/{codigo}, /admin/codigos-qr/{id}/descargar/{formato}, /invitaciones/{token}) y api.php (/api/v1, /api/v1/panel)
└── tests/Feature/          # Suite completa de pruebas automatizadas en PHPUnit (770 pruebas)
```

---

## Autores

- [@MarioJavaScript](https://github.com/MarioJavaScript)
- [@Elproshaitron-stack](https://github.com/elproshaitron-stack)
