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
- [ ] Menú público `/m/{slug}` con Blade + Tailwind *(pendiente — Mitad B)*
- [ ] Redirección `/q/{codigo}` y descarga de QR en PNG/SVG/PDF *(CRUD de `codigos_qr` en panel listo; faltan rutas públicas y exportación — Mitad B)*
- [x] Roles Propietario y Editor (`MatrizPolicy`, policies por entidad y 2FA para Propietario/Superadmin)
- [ ] Despliegue en VPS + Cloudflare (`MenuCacheService` y `PurgarCacheRestauranteJob` listos; falta conectar en `/m/{slug}` y configurar VPS)

## Fase 2: Primeros clientes

- [ ] Piloto con 3 a 5 restaurantes
- [ ] Pedido por WhatsApp (carrito en navegador y deep-link `wa.me` — *pendiente en Mitad B*)
- [x] Platillos agotados y precio de oferta en BD y panel *(falta visualización en menú público `/m/{slug}` — Mitad B)*
- [ ] Respaldos diarios fuera del VPS

## Fase 3: Funcionalidades avanzadas v1.0

- [x] Multi-idioma y traducciones en BD y panel (`HasTraducciones`, `TraduccionesRepeater`) *(falta selector e idioma en `/m/{slug}` — Mitad B)*
- [x] Variantes con precio propio en BD y panel *(falta selector de variante en `/m/{slug}` y carrito — Mitad B)*
- [x] Etiquetas (`vegano`, `vegetariano`, `picante`, `sin_gluten`, `sin_lactosa`, `nuevo`, `recomendado`) en BD, seeder y panel *(falta mostrar insignias en `/m/{slug}` — Mitad B)*
- [x] Invitaciones por email (`InvitacionService`, `InvitacionAceptarController`) e historial de cambios (`Auditable`, `HistorialCambioResource`)
- [x] Superadmin: alta y suspensión de restaurantes (`RestauranteResource`, `UserResource`)
- [ ] NFC (`codigos_acceso` con `tipo`)

## Fuera de alcance por ahora

Pagos en línea, pedidos guardados, estadísticas, sucursales, dominio propio y facturación.
