# MENUONL

Menú digital con QR (y NFC) para restaurantes, multi-restaurante.

El comensal escanea un QR, ve el menú sin instalar nada y, si el restaurante lo permite, envía su pedido por WhatsApp. Cada restaurante administra platillos, precios, fotos, contactos y horarios desde un panel web.

**Estado:** especificación v1.0 (15 de octubre de 2026). Sin código todavía.

## Stack propuesto

| Componente | Tecnología |
| --- | --- |
| Aplicación | Laravel 11 (PHP-FPM + OPcache) |
| Menú público | Blade + Tailwind CSS |
| Panel de administración | Filament 3 |
| Base de datos | MariaDB 10.6+ (InnoDB, utf8mb4) |
| Caché y sesiones | Redis |
| Colas | Supervisor + colas de Laravel |
| Servidor web | Nginx |
| CDN / DNS / WAF | Cloudflare (plan gratuito) |

## Especificaciones

Resumen de la especificación v1.0. El detalle completo está en [docs/01-especificacion.md](docs/01-especificacion.md).

### Actores

| Actor | Qué hace |
| --- | --- |
| Comensal | Escanea el QR, consulta el menú y envía su pedido por WhatsApp. No necesita cuenta. |
| Propietario | Acceso total a su restaurante: menú, precios, contactos, configuración y usuarios. |
| Editor | Modifica menú, precios, contactos y horarios. No gestiona usuarios ni configuración. |
| Superadmin | Da de alta, suspende y reactiva restaurantes. |

### Funcionalidades principales

- **Menú público** (`/m/{slug}`): categorías, platillos, variantes con precio propio, ofertas, etiquetas, fotos, multi-idioma, contactos, horarios y estado abierto/cerrado.
- **Pedido por WhatsApp:** carrito en el navegador y mensaje prellenado con mesa y total (sin pagos, sin guardar el pedido).
- **Panel de administración** (`/admin`): CRUD y ordenamiento por arrastre, edición rápida de precios, subida de imágenes convertidas a WebP, traducciones, configuración del restaurante e historial de cambios.
- **Códigos QR:** generales o por mesa (PNG, SVG, PDF) con URL corta `/q/{codigo}` que sobrevive a cambios de slug.
- **Multi-restaurante:** un usuario puede administrar varios restaurantes; roles Propietario y Editor; invitaciones por email.

### Requisitos no funcionales clave

| Área | Requisito |
| --- | --- |
| Rendimiento | Menú completo en menos de 1.5 s con 4G desde Latinoamérica; HTML generado en el servidor. |
| Caché | Redis en el VPS + Cloudflare; se purgan al guardar cambios. |
| Aislamiento | Todas las consultas filtran por `restaurante_id`. |
| Seguridad | HTTPS, argon2/bcrypt, CSRF, límite de intentos, tokens de invitación con caducidad. |
| Datos | MariaDB 10.6+, InnoDB, utf8mb4; precios en `DECIMAL(10,2)`; borrado lógico. |
| Respaldos | Diarios, 14 días de retención, fuera del VPS. |
| Disponibilidad | Si el VPS cae, Cloudflare sirve el último menú en caché. |

## Documentación

| Documento | Contenido |
| --- | --- |
| [docs/01-especificacion.md](docs/01-especificacion.md) | Requisitos, arquitectura, modelo de datos y alcance |
| [docs/02-seguridad.md](docs/02-seguridad.md) | Recomendaciones de seguridad (SQL, imágenes, XSS, cuentas, infraestructura) |
| [docs/03-nfc-y-ventas.md](docs/03-nfc-y-ventas.md) | Propuesta de NFC y estrategia de venta |
| [docs/04-roadmap-mvp.md](docs/04-roadmap-mvp.md) | Plan por fases empezando por un MVP mínimo |
| [docs/05-pendientes.md](docs/05-pendientes.md) | Decisiones abiertas |

## Estructura prevista

```
menuonl/
├── README.md
└── docs/
```

El código de la aplicación Laravel se agregará en la raíz cuando arranque la Fase 0 del roadmap.

## Autores

- [@MarioJavaScript](https://github.com/MarioJavaScript)
- [@Elproshaitron](https://github.com/Elproshaitron)
