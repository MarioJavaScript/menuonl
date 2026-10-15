# MENUONL

Menú digital con QR (y NFC) para restaurantes, multi-restaurante.

El comensal escanea un QR, ve el menú al instante sin instalar ninguna app y, si el restaurante lo tiene habilitado, arma un carrito y envía su pedido por WhatsApp. Cada restaurante administra sus platillos, precios, variantes, fotos, contactos y horarios desde un panel web responsivo.

**Estado:** Especificación v1.0 completa y esquema DDL finalizado (15 de octubre de 2026). Listo para inicialización del entorno y código base (Fase 0).

---

## Stack Tecnológico

| Componente | Tecnología | Propósito |
| --- | --- | --- |
| **Backend & Framework** | Laravel 11 (PHP 8.2+ con OPcache) | Lógica de negocio, APIs internas y renderizado SSR |
| **Menú público** | Blade + Tailwind CSS | Páginas ultraligeras, accesibles, en una sola petición HTML |
| **Panel de administración** | Filament 3 | CRUD administrativo, ordenamiento drag-and-drop y formularios |
| **Base de datos** | MariaDB 10.6+ (InnoDB, utf8mb4) | Persistencia relacional, integridad referencial y borrado lógico |
| **Caché y sesiones** | Redis 6+ / 7+ | Caché L1 en VPS (menús armados) y almacén de sesiones rápidas |
| **Colas de trabajo** | Supervisor + Laravel Queues | Procesamiento asíncrono (WebP, purgas CDN, envío de emails) |
| **Servidor web** | Nginx | Terminación TLS, compresión HTTP y entrega estática |
| **CDN / DNS / WAF** | Cloudflare (Edge Network) | Caché L2 en servidores de borde (LATAM), protección DDoS y SSL |

---

## Especificaciones Generales

### Actores del Sistema

| Actor | Responsabilidad y Permisos |
| --- | --- |
| **Comensal** | Escanea el QR, consulta el menú público y envía su pedido por WhatsApp. No requiere registro ni aplicación instalada. |
| **Propietario** | Acceso total a su restaurante: menú, precios, contactos, configuración, horarios y gestión de usuarios (invitaciones). |
| **Editor** | Personal operativo del restaurante. Modifica menú, precios, contactos y horarios. Sin acceso a usuarios ni configuración general. |
| **Superadmin** | Operador global de MENUONL. Da de alta, suspende y reactiva restaurantes en la plataforma. |

### Funcionalidades Principales

- **Menú público (`/m/{slug}`):** categorías, platillos, variantes con precio propio, ofertas (precio tachado), etiquetas dietéticas, fotos WebP con *lazy-loading*, multi-idioma con fallback, información de contacto, horarios y estado dinámico abierto/cerrado.
- **Pedido por WhatsApp:** carrito en navegador local del comensal y deep-link prellenado con desglose de productos, número de mesa y total (sin pagos en línea ni persistencia en BD en v1.0).
- **Panel de administración (`/admin`):** CRUD y reordenamiento drag-and-drop con Filament 3, tabla rápida de edición de precios y disponibilidad, conversor de imágenes a WebP, captura de traducciones y log de auditoría.
- **Códigos QR:** generación para descarga en PNG, SVG o PDF imprimible (general o por mesa), direccionados a `/q/{codigo}` para desacoplar el material físico de posibles cambios de slug.
- **Multi-restaurante:** administración de múltiples sedes/restaurantes bajo una misma cuenta de usuario con invitaciones seguras por correo.

### Requisitos No Funcionales Clave

| Área | Requisito |
| --- | --- |
| **Rendimiento** | Menú público optimizado para móvil; carga completa en menos de **1.5 s** sobre 4G en Latinoamérica. HTML generado en servidor (SSR). |
| **Caché en 2 niveles** | Almacenamiento L1 en Redis (VPS) + L2 en Cloudflare CDN; purga coordinada en segundos al guardar cambios. |
| **Aislamiento multi-tenant** | Filtrado obligatorio por `restaurante_id` en todas las consultas del panel mediante Global Scope. |
| **Seguridad** | HTTPS obligatorio, hashing Argon2id/Bcrypt, protección CSRF, rate-limiting contra ataques de fuerza bruta y tokens con caducidad. |
| **Consistencia monetaria** | Precios en `DECIMAL(10,2)` en MariaDB (nunca punto flotante). |
| **Integridad y respaldo** | Borrado lógico (`deleted_at`) en restaurantes, categorías y platillos; respaldos diarios automáticos retenidos por 14 días fuera del VPS. |
| **Disponibilidad** | Si el VPS se reinicia o sufre interrupciones temporales, Cloudflare entrega la última versión en caché a los comensales. |

---

## Arquitectura y Decisiones Técnicas

### 1. Mitigación de Latencia (VPS en Finlandia vs. Usuarios en LATAM)
El servidor VPS se encuentra en Finlandia y los comensales en Latinoamérica (150–250 ms de RTT por viaje de red). Para garantizar una carga menor a **1.5 s** con 4G:
- **Cloudflare CDN** se ubica delante del VPS para almacenar en caché de borde el HTML del menú público (`/m/*`) y las imágenes WebP. La respuesta en borde toma entre **30 y 80 ms**.
- **Purga instantánea de caché:** al modificar precios o platillos en Filament, un job en cola de Redis invalida la clave local y dispara la purga vía API de Cloudflare.
- **SSR sin SPAs pesadas:** Menú generado en una sola petición HTML con CSS mínimo y *lazy loading* de imágenes.

### 2. Aislamiento Multi-restaurante Estricto
- Todas las consultas del panel filtran obligatoriamente por `restaurante_id`.
- Se implementará un **Global Scope** en Eloquent en los modelos dependientes (`Producto`, `Categoria`, `Contacto`, `Horario`, `CodigoQr`, etc.).
- Las llaves foráneas utilizan `ON DELETE CASCADE` en cascada relacional interna.

### 3. Modelo de Datos y Esquema DDL
El esquema completo está definido en [`docs/esquema.sql`](docs/esquema.sql) con 14 tablas optimizadas:
- `usuarios`: Administradores y editores con contraseñas en Bcrypt/Argon2id.
- `restaurantes`: Configuración central, slugs únicos, zona horaria y switch de pedidos WhatsApp.
- `restaurante_usuario`: Tabla pivote de asignación con roles `propietario` y `editor`.
- `invitaciones`: Tokens criptográficos aleatorios con caducidad (7 días).
- `restaurante_idiomas`: Catálogo de idiomas habilitados por restaurante.
- `contactos`: Canales de comunicación (teléfono, WhatsApp, dirección, redes) con orden y visibilidad.
- `horarios`: Horarios por día de la semana (0=Domingo..6=Sábado) con soporte para turnos partidos.
- `categorias` y `categoria_traducciones`: Agrupación de platillos con traducciones desacopladas.
- `productos` y `producto_traducciones`: Platillos, precios en `DECIMAL(10,2)`, fotos e insignias.
- `producto_variantes` y `variante_traducciones`: Tamaños/presentaciones con precio propio.
- `etiquetas` y `producto_etiqueta`: Catálogo global de insignias (`vegano`, `picante`, `sin_gluten`, etc.).
- `codigos_qr`: URLs cortas `/q/{codigo}` desacopladas para preservar material impreso físico.
- `historial_cambios`: Auditoría forense de cambios con snapshots JSON (`antes` / `después`).

### 4. Flujo de Pedidos por WhatsApp
- Carrito 100% en el navegador (cliente local).
- Generación de enlace deep-link `https://wa.me/{numero}?text=...` con el pedido formateado y mesa asignada.
- No se persisten órdenes en base de datos en la versión v1.0, minimizando complejidad operativa.

---

## Prerrequisitos del Entorno (Fase 0)

Para configurar el entorno de desarrollo local o preparar el servidor de despliegue:

| Herramienta | Versión mínima | Comentarios |
| --- | --- | --- |
| **PHP** | 8.2+ | Extensiones: `pdo_mysql`, `redis`, `gd` o `imagick`, `intl`, `mbstring`, `xml`, `curl`, `zip`, `bcmath` |
| **Composer** | 2.6+ | Gestor de paquetes PHP |
| **Node.js & NPM** | Node 18+ o 20+ (LTS) | Compilación de assets frontend con Vite / Tailwind CSS |
| **MariaDB** | 10.6+ (o MySQL 8.0+) | Motor InnoDB, `utf8mb4_unicode_ci` |
| **Redis** | 6.2+ / 7.x | Caché y colas locales |
| **Git** | 2.30+ | Control de versiones |

---

## Guía de Preparación del Entorno (Paso a Paso)

El siguiente `/boost` o desarrollador a cargo de la **Fase 0 (Base del proyecto)** debe seguir esta secuencia:

### 1. Clonación y Verificación del Repositorio
```bash
git clone https://github.com/MarioJavaScript/menuonl.git
cd menuonl
git status
```

### 2. Configuración de Base de Datos
Crear la base de datos local en MariaDB:
```sql
CREATE DATABASE menuonl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
*(Opcional para inspección directa)*: se puede cargar el esquema de referencia:
```bash
mariadb -u root -p menuonl < docs/esquema.sql
```

### 3. Inicialización del Proyecto Laravel 11
En la raíz del repositorio:
```bash
composer create-project laravel/laravel . --prefer-dist
```
Configurar en `.env`:
```env
APP_NAME="MENUONL"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=menuonl
DB_USERNAME=root
DB_PASSWORD=tu_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. Instalación de Filament 3 y Tailwind CSS
```bash
composer require filament/filament:"^3.2"
php artisan filament:install --panels
npm install -D tailwindcss postcss autoprefixer
npm install
```

### 5. Creación de Migraciones y Seeders
Generar las migraciones siguiendo la estructura exacta de [`docs/esquema.sql`](docs/esquema.sql):
- Migración 1: `usuarios` (adaptando campos estándar de Laravel con `es_superadmin`, `activo`, `ultimo_acceso`).
- Migración 2: `restaurantes`, `restaurante_usuario`, `invitaciones`, `restaurante_idiomas`.
- Migración 3: `contactos`, `horarios`.
- Migración 4: `categorias`, `categoria_traducciones`.
- Migración 5: `productos`, `producto_traducciones`, `producto_variantes`, `variante_traducciones`.
- Migración 6: `etiquetas`, `producto_etiqueta` (con seeder de las 7 etiquetas base).
- Migración 7: `codigos_qr`, `historial_cambios`.

### 6. Implementación del Tenant Global Scope
Crear el trait o scope `BelongsToRestaurant` para que todo modelo aplique automáticamente:
```php
builder->where('restaurante_id', auth()->user()->current_restaurant_id);
```
y configurar pruebas automáticas con Pest / PHPUnit para verificar que ningún usuario acceda a datos de otro restaurante.

---

## Documentación del Repositorio

Todos los detalles técnicos y especificaciones funcionales se encuentran en la carpeta [`docs/`](docs/):

| Documento | Descripción |
| --- | --- |
| [docs/01-especificacion.md](docs/01-especificacion.md) | Especificación funcional completa v1.0, actores, diccionario de datos y diagramas de flujo Mermaid |
| [docs/02-seguridad.md](docs/02-seguridad.md) | Guía de hardening: sanitización de WebP, mitigación SQLi/XSS, CSRF, aislamiento de tenants y rate limiting |
| [docs/03-nfc-y-ventas.md](docs/03-nfc-y-ventas.md) | Integración con chips NFC físicos NTAG213/215, modelos de tags y estrategia comercial de venta |
| [docs/04-roadmap-mvp.md](docs/04-roadmap-mvp.md) | Plan de desarrollo por fases (Fase 0 a Fase 3) enfocado en un MVP para los primeros 3 a 5 restaurantes |
| [docs/05-pendientes.md](docs/05-pendientes.md) | Registro de decisiones técnicas, estado de pendientes y temas de infraestructura |
| [docs/esquema.sql](docs/esquema.sql) | DDL MariaDB 10.6+ completo con llaves foráneas, índices compuestos y datos iniciales de etiquetas |
| [docs/especificacion.docx](docs/especificacion.docx) | Documento fuente de especificación funcional en formato Microsoft Word |

---

## Estructura Prevista del Repositorio

```
menuonl/
├── .gitignore
├── AUTHORS.md
├── README.md
└── docs/
    ├── 01-especificacion.md
    ├── 02-seguridad.md
    ├── 03-nfc-y-ventas.md
    ├── 04-roadmap-mvp.md
    ├── 05-pendientes.md
    ├── esquema.sql
    └── especificacion.docx
```
*(Los archivos de la aplicación Laravel 11 se inicializarán en la raíz durante la Fase 0).*

---

## Autores

- [@MarioJavaScript](https://github.com/MarioJavaScript)
- [@Elproshaitron-stack](https://github.com/elproshaitron-stack)
