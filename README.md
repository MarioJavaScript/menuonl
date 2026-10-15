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
