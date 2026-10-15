# Roadmap: empezar por un MVP mínimo

Objetivo: tener 3 a 5 restaurantes reales usando el sistema lo antes posible y construir el resto según lo que pidan.

## Fase 0: Base del proyecto

- [ ] Crear proyecto Laravel 11 + Filament 3
- [ ] Configurar MariaDB, Redis y entorno local
- [ ] Migraciones basadas en [docs/esquema.sql](esquema.sql): base inicial con `usuarios`, `restaurantes`, `restaurante_usuario` y seeders de etiquetas
- [ ] Global Scope por `restaurante_id` + tests de aislamiento
- [ ] CI básico (tests + `composer audit`)

## Fase 1: MVP (menú + QR)

- [ ] Categorías y productos (un solo idioma, sin variantes todavía)
- [ ] Subida de imágenes con reprocesado a WebP (ver [02-seguridad.md](02-seguridad.md))
- [ ] Contactos y horarios, con estado abierto/cerrado
- [ ] Menú público `/m/{slug}` con Blade + Tailwind
- [ ] `codigos_qr` y redirección `/q/{codigo}`; descarga de QR (PNG/PDF)
- [ ] Roles Propietario y Editor
- [ ] Despliegue en VPS + Cloudflare (caché y purga al guardar)

## Fase 2: Primeros clientes

- [ ] Piloto con 3 a 5 restaurantes
- [ ] Pedido por WhatsApp (carrito)
- [ ] Platillos agotados y precio de oferta
- [ ] Respaldos diarios fuera del VPS

## Fase 3: Lo que pidan los clientes

- [ ] Multi-idioma y traducciones
- [ ] Variantes con precio propio
- [ ] Etiquetas (vegano, picante, sin gluten)
- [ ] Invitaciones por email y historial de cambios
- [ ] Superadmin: alta y suspensión de restaurantes
- [ ] NFC (`codigos_acceso` con `tipo`)

## Fuera de alcance por ahora

Pagos en línea, pedidos guardados, estadísticas, sucursales, dominio propio y facturación.
