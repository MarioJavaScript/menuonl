# Roadmap: empezar por un MVP mínimo

Objetivo: tener 3 a 5 restaurantes reales usando el sistema lo antes posible y construir el resto según lo que pidan.

## Fase 0: Base del proyecto (Completada)

- [x] Crear proyecto Laravel + Filament
- [x] Configurar MariaDB, Redis y entorno local (PHP 8.3, Composer 2, MariaDB/SQLite)
- [x] Migraciones basadas en [docs/esquema.sql](esquema.sql): las 14 tablas completas del esquema v1.0
- [x] Global Scope por `restaurante_id` (`RestauranteScope` y `BelongsToRestaurante`) + tests de aislamiento
- [x] CI básico (GitHub Actions con tests + `composer audit`)

## Fase 1: MVP (menú + QR)

- [x] Categorías y productos en panel administrativo (`/admin`) con reordenamiento y edición rápida
- [x] Subida de imágenes con reprocesado a WebP (`WebpImageService` + ver [02-seguridad.md](02-seguridad.md))
- [x] Contactos y horarios en panel, con cálculo de estado abierto/cerrado (`Restaurante::estaAbiertoAhora()`)
- [x] Menú público `/m/{slug}` con Blade + Tailwind CSS v4, multi-idioma, variantes, etiquetas y horarios dinámicos
- [x] Redirección `/q/{codigo}` y descarga de QR en PNG/SVG/PDF + generación por lote de mesas en Filament 5
- [x] Roles Propietario y Editor (`MatrizPolicy`, policies por entidad y 2FA para Propietario/Superadmin)
- [x] Conexión de caché L1/L2 (`MenuCacheService`) en `/m/{slug}` y cabeceras de seguridad estrictas (CSP, nosniff, SAMEORIGIN)
- [ ] Despliegue en VPS + Cloudflare (pendiente de infraestructura de producción)

## Fase 2: Primeros clientes

- [ ] Piloto con 3 a 5 restaurantes
- [x] Pedido por WhatsApp (carrito en navegador con `localStorage` y deep-link estructurado `wa.me`)
- [x] Platillos agotados y precio de oferta en BD, panel y visualización en menú público `/m/{slug}`
- [ ] Respaldos diarios fuera del VPS (código listo, falta disco y prueba en VPS)

## Fase 3: Funcionalidades avanzadas v1.0

- [x] Multi-idioma y traducciones en BD, panel y menú público (`?lang=`, `Accept-Language`, default)
- [x] Variantes con precio propio en BD, panel, menú público y carrito
- [x] Etiquetas (`vegano`, `vegetariano`, `picante`, `sin_gluten`, `sin_lactosa`, `nuevo`, `recomendado`) en BD, seeder, panel y menú público
- [x] Invitaciones por email (`InvitacionService`, `InvitacionAceptarController`) e historial de cambios (`Auditable`, `HistorialCambioResource`)
- [x] Superadmin: alta y suspensión de restaurantes (`RestauranteResource`, `UserResource`)
- [ ] NFC (`codigos_acceso` con `tipo`)

## Fuera de alcance por ahora

Pagos en línea, pedidos guardados, estadísticas, sucursales, dominio propio y facturación.
