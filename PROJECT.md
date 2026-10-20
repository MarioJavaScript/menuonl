# Project: MENUONL Public Menu Decoupling

## Architecture
Decoupled client-server architecture consisting of:
1. **Backend (Laravel 11 / PHP 8.3)**:
   - Headless REST API under `/api/v1/menu/{slug}`.
   - Built with Eloquent API Resources (`app/Http/Resources/Api/V1/*`).
   - Tenant isolation via `RestauranteScope`.
   - Real-time business logic: opening hours evaluation, order eligibility (`puede_pedir`), currency formatting, dynamic monogram calculation.
   - CORS enabled for frontend clients (`http://localhost:3000`).
   - Automated Feature Test suite in `tests/Feature/Api/PublicMenuApiTest.php`.
2. **Frontend (Next.js 15 / React 19 / TypeScript / Tailwind CSS / App Router)**:
   - Located in `frontend/`.
   - Modern App Router with dynamic routing `/m/[slug]` and `/[slug]`.
   - Client API consumer in `frontend/src/lib/api.ts` configured via `NEXT_PUBLIC_API_URL`.
   - Mobile-first responsive UI (tested at 360px, 768px, 1280px).
   - Sticky category navigation with smooth scroll.
   - Interactive WhatsApp Cart system with table/takeaway selection and formatted URL generator.
   - Dark/Light/System theme mode with `localStorage` persistence.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | API Routing & Bootstrap | Register `api.php` route file in `bootstrap/app.php` with `/api/v1/menu/{slug}` route | M1 | Backend Survey |
| 2 | CORS Configuration | Create `config/cors.php` allowing `http://localhost:3000` with full methods/headers | M1 | ORIGINAL_REQUEST R1 |
| 3 | Restaurant Identity Resource | Expose name, slug, slogan, description, logo, cover, monogram, theme, accent colors | M1 | ORIGINAL_REQUEST R1 |
| 4 | Real-time Open/Closed Status | Dynamically evaluate `abierto` based on restaurant schedule & shifts in real time | M1 | ORIGINAL_REQUEST R1 |
| 5 | Order Eligibility Flag | Calculate `puede_pedir` from active orders, open status, and WhatsApp number | M1 | ORIGINAL_REQUEST R1 |
| 6 | Sorted Categories & Translations | Expose visible categories sorted by `orden` with localized translations | M1 | ORIGINAL_REQUEST R1 |
| 7 | Dishes & Formatted Prices | Expose dishes with formatted pricing, offer prices, images, and availability | M1 | ORIGINAL_REQUEST R1 |
| 8 | Dietary Tags & Variants | Expose dietary tags with icons, and variants with individual formatted prices | M1 | ORIGINAL_REQUEST R1 |
| 9 | Multilingual & Fallback | Support `?lang=` query param and `Accept-Language` header with default fallback | M1 | ORIGINAL_REQUEST R1 |
| 10 | Multi-Tenant Scoping | Strict isolation ensuring slug A only queries and exposes tenant A data | M1 | ORIGINAL_REQUEST R1 |
| 11 | Backend Feature Test Suite | Implement comprehensive tests in `tests/Feature/Api/PublicMenuApiTest.php` | M1 | ORIGINAL_REQUEST R1 & R4 |
| 12 | Next.js 15 Project Scaffolding | Initialize Next.js 15 with TypeScript, Tailwind CSS, ESLint, App Router in `frontend/` | M2 | ORIGINAL_REQUEST R2 |
| 13 | Frontend Environment & Config | Configure `.env.local` / `.env.example` with `NEXT_PUBLIC_API_URL`, setup `next.config.ts` | M2 | ORIGINAL_REQUEST R2 |
| 14 | API Client & TypeScript Types | Implement type-safe API client and TypeScript models matching Eloquent resources | M2 | ORIGINAL_REQUEST R2 |
| 15 | Header & Identity Component | Display cover, logo/monogram, hours, live open/closed status badge, table info | M3 | ORIGINAL_REQUEST R3 |
| 16 | Sticky Category Navigation | Horizontal sticky category bar with smooth scrolling and active item highlight | M3 | ORIGINAL_REQUEST R3 |
| 17 | Responsive Dish Cards | 1 col mobile, 2 col tablet/desktop cards with prices, tags, variants, and sold-out states | M3 | ORIGINAL_REQUEST R3 |
| 18 | Interactive WhatsApp Cart | Floating bottom bar, slide-up drawer/modal, quantities, dish notes, table/takeaway | M3 | ORIGINAL_REQUEST R3 |
| 19 | WhatsApp Message Builder | Format structured order message and launch `https://wa.me/{phone}?text=...` | M3 | ORIGINAL_REQUEST R3 |
| 20 | Theme Mode Switcher | Light / Dark / System segmented selector with `localStorage` persistence | M3 | ORIGINAL_REQUEST R3 |
| 21 | Responsive Layout Optimization | Ensure seamless UX across 360px mobile, 768px tablet, 1280px desktop | M3 | ORIGINAL_REQUEST R3 |
| 22 | Backend Quality & Pint Formatting | Run `vendor/bin/pint --format agent` and verify all tests pass 100% | M4 | ORIGINAL_REQUEST R4 |
| 23 | Frontend Production Build | Verify `npm run build` compiles cleanly with zero TS errors in `frontend/` | M4 | ORIGINAL_REQUEST R4 |
| 24 | Final Verification & Delivery | End-to-end integration test, concurrent execution documentation, victory handoff | M4 | ORIGINAL_REQUEST R4 |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Laravel Public Menu REST API & CORS & Tests | Features 1–11: `routes/api.php`, `config/cors.php`, `app/Http/Controllers/Api/V1/*`, `app/Http/Resources/Api/V1/*`, `tests/Feature/Api/PublicMenuApiTest.php` | None | DONE (20/20 tests passing, Pint clean) |
| M2 | Next.js 15 Frontend Initialization & API Client | Features 12–14: Initialize `frontend/`, configure TypeScript, Tailwind, API client, types | None (can run in parallel or following M1 contract) | DONE (Verified: tsc, lint, tests, build) |
| M3 | Next.js 15 Public Menu Interface & WhatsApp Cart | Features 15–21: UI components, header, category bar, dish cards, cart drawer, WhatsApp formatting, theme switch | M1, M2 | DONE (Verified: tsc, lint, 19 tests, build) |
| M4 | Final Quality, Build, Pint & Victory Delivery | Features 22–24: `php artisan test`, `npm run build`, Pint formatting, integrity audit, handoff | M1, M2, M3 | DONE (All tests passing, Turbopack build clean, Pint clean, Gate PASS) |

## Interface Contracts
### Laravel Backend API ↔ Next.js Frontend Client
- **Base URL**: `http://localhost:8000/api/v1` (configured via `NEXT_PUBLIC_API_URL`)
- **Endpoint**: `GET /api/v1/menu/{slug}?lang={lang}&mesa={mesa}`
- **Headers**:
  - `Accept: application/json`
  - `Origin: http://localhost:3000`
- **Response Format**:
  - `200 OK`: JSON object `{ "data": { "id": number, "nombre": string, "slug": string, "eslogan": string|null, "logo_url": string|null, "monograma": string, "tema": string, "color_acento": string|null, "moneda": string, "idioma": string, "abierto": boolean, "pedidos_activos": boolean, "puede_pedir": boolean, "whatsapp_pedidos": string|null, "whatsapp_url": string|null, "mostrar_agotados": boolean, "mesa": string|null, "horarios": [...], "contactos": [...], "categorias": [ { "id": number, "orden": number, "nombre": string, "descripcion": string|null, "platillos": [ { "id": number, "categoria_id": number, "nombre": string, "descripcion": string|null, "precio": number, "precio_formateado": string, "precio_oferta": number|null, "precio_oferta_formateado": string|null, "precio_vigente": number, "precio_vigente_formateado": string, "tiene_oferta": boolean, "imagen_url": string|null, "disponible": boolean, "destacado": boolean, "orden": number, "etiquetas": [...], "variantes": [...] } ] } ], "destacados": [...] } }`
  - `404 Not Found`: `{ "message": "Restaurante no encontrado o no disponible." }`
  - `429 Too Many Requests`: `{ "message": "Too Many Requests" }`

### WhatsApp Message Contract
- **Format**:
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

## Code Layout
### Backend (Laravel)
- `routes/api.php` — API routes
- `bootstrap/app.php` — Route registration & API middleware
- `config/cors.php` — CORS configuration
- `app/Http/Controllers/Api/V1/MenuApiController.php` — API controller
- `app/Http/Resources/Api/V1/`
  - `RestaurantResource.php`
  - `CategoryResource.php`
  - `DishResource.php`
  - `DishVariantResource.php`
  - `DietaryTagResource.php`
  - `HorarioResource.php`
  - `ContactoResource.php`
- `tests/Feature/Api/PublicMenuApiTest.php` — Automated feature tests

### Frontend (Next.js 15)
- `frontend/package.json`
- `frontend/next.config.ts`
- `frontend/tailwind.config.ts`
- `frontend/src/app/layout.tsx`
- `frontend/src/app/page.tsx`
- `frontend/src/app/m/[slug]/page.tsx`
- `frontend/src/app/[slug]/page.tsx`
- `frontend/src/types/menu.ts`
- `frontend/src/lib/api.ts`
- `frontend/src/context/CartContext.tsx`
- `frontend/src/components/`
  - `HeroHeader.tsx`
  - `CategoryNav.tsx`
  - `DishCard.tsx`
  - `CartFloatingBar.tsx`
  - `CartDrawer.tsx`
  - `ThemeSwitcher.tsx`
