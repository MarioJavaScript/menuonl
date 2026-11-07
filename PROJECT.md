# Project: MENUONL

Multi-restaurant digital menu with QR/NFC. Everything is served by one Laravel application; there is no separate frontend project (the experimental Next.js client under `frontend/` was removed and remains only in git history).

## Architecture
Laravel 13 / PHP 8.3 monolith:
1. **Public pages (Blade + Tailwind v4 + Vite)**
   - Restaurant site at `/{slug}` (templates `a` to `g`, `resources/views/sitio`) and menu at `/{slug}/menu` (`resources/views/menu`).
   - QR redirect `/q/{codigo}`; old `/m/{slug}` and `/s/{slug}` only redirect (301).
   - `/{slug}` is a catch-all and must stay last in `routes/web.php`; new first-level routes must be added to `Catalogos::slugsReservados()`.
2. **Back office `/admin` (Filament 5)**: platform and configuration work (restaurants, users, invitations, QR codes, contacts, change history, full menu authoring with translations and variants). Authority: policies and `User::canAccessPanel`.
3. **Service panel `/panel` (Blade + Vite)**: daily operation (reservations, orders, quick menu edits, settings, team). It talks to `/api/v1/panel/{restaurante:slug}` with the login session; modules per role are defined in `App\Services\PermisosPanel` and enforced by `ModuloPanel`.
4. **Public REST API** `GET /api/v1/menu/{slug}` (Eloquent API Resources in `app/Http/Resources/Api/V1`), plus public reservations under `/api/v1/sitio/{slug}`.
   - CORS: no origin is allowed by default; set `FRONTEND_URL` to allow an external browser client (`config/cors.php`).
5. **Cross-cutting**: tenant isolation via `RestauranteScope`, menu cache (`MenuCacheService`), WebP image pipeline (`WebpImageService`), encrypted backups (`RespaldoService`), audit trail (`Auditable`).

## Public menu API contract
- **Endpoint**: `GET /api/v1/menu/{slug}?lang={lang}&mesa={mesa}`
- **Headers**: `Accept: application/json`
- **Responses**:
  - `200 OK`: `{ "data": { restaurant identity, hours, contacts, `abierto`, `puede_pedir`, `categorias` with `platillos` (formatted prices, offers, tags, variants), `destacados` } }`. See `app/Http/Resources/Api/V1/*` and `tests/Feature/Api/PublicMenuApiTest.php` for the exact shape.
  - `404 Not Found`: `{ "message": "Restaurante no encontrado o no disponible." }`
  - `429 Too Many Requests`: `{ "message": "Too Many Requests" }`

## WhatsApp message contract
```text
Hola, quiero pedir en *{nombreRestaurante}*:

*Mesa:* {mesa || 'Para llevar'}

*Pedido:*
• {cantidad}x {nombre} [({variante_nombre})] - {subtotal_formateado}
  _Nota: {nota}_

*Nota para la cocina:* {notasGenerales}

*Total:* {total_formateado}
```
- **Link**: `https://wa.me/{whatsapp_limpio}?text={encodedText}`
