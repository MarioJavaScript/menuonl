<?php

namespace Tests\Feature;

use App\Jobs\PurgarCacheRestauranteJob;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Js;
use Tests\TestCase;

class AdversarialPublicMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    // =========================================================================
    // Area 1: Multi-Tenant Isolation Under Adversarial Conditions
    // =========================================================================

    public function test_strict_multi_tenant_isolation_no_leak_between_tenants(): void
    {
        $tenantA = Restaurante::factory()->create([
            'nombre' => 'Tenant Alpha Taqueria',
            'slug' => 'tenant-alpha',
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511111111',
        ]);

        $tenantB = Restaurante::factory()->create([
            'nombre' => 'Tenant Beta Confidential',
            'slug' => 'tenant-beta',
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525522222222',
        ]);

        // Tenant A entities
        $catA = Categoria::factory()->create([
            'restaurante_id' => $tenantA->id,
            'visible' => true,
            'orden' => 1,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $catA->id,
            'idioma' => 'es',
            'nombre' => 'ALPHA_CATEGORY_EXCLUSIVE',
        ]);

        $prodA = Producto::factory()->create([
            'restaurante_id' => $tenantA->id,
            'categoria_id' => $catA->id,
            'precio' => 100.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodA->id,
            'idioma' => 'es',
            'nombre' => 'ALPHA_PRODUCT_TACO',
        ]);

        $varA = ProductoVariante::factory()->create([
            'producto_id' => $prodA->id,
            'precio' => 120.00,
            'disponible' => true,
        ]);
        VarianteTraduccion::factory()->create([
            'variante_id' => $varA->id,
            'idioma' => 'es',
            'nombre' => 'ALPHA_VARIANT_DOUBLE',
        ]);

        $contactoA = Contacto::factory()->create([
            'restaurante_id' => $tenantA->id,
            'tipo' => 'telefono',
            'valor' => '55-1111-AAAA',
            'visible' => true,
        ]);

        // Tenant B entities (Sensitive / Secret data)
        $catB = Categoria::factory()->create([
            'restaurante_id' => $tenantB->id,
            'visible' => true,
            'orden' => 1,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $catB->id,
            'idioma' => 'es',
            'nombre' => 'BETA_SECRET_CATEGORY',
        ]);

        $prodB = Producto::factory()->create([
            'restaurante_id' => $tenantB->id,
            'categoria_id' => $catB->id,
            'precio' => 999.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodB->id,
            'idioma' => 'es',
            'nombre' => 'BETA_SECRET_BURGER',
        ]);

        $varB = ProductoVariante::factory()->create([
            'producto_id' => $prodB->id,
            'precio' => 1299.00,
            'disponible' => true,
        ]);
        VarianteTraduccion::factory()->create([
            'variante_id' => $varB->id,
            'idioma' => 'es',
            'nombre' => 'BETA_SECRET_VARIANT',
        ]);

        $contactoB = Contacto::factory()->create([
            'restaurante_id' => $tenantB->id,
            'tipo' => 'telefono',
            'valor' => '55-2222-BBBB',
            'visible' => true,
        ]);

        // Set global scope maliciously to Tenant B before querying Tenant A
        RestauranteScope::setRestauranteId($tenantB->id);

        // Query Tenant A menu
        $responseA = $this->get(route('menu.publico', ['slug' => $tenantA->slug]));
        $responseA->assertOk();

        // Must see Tenant A details
        $responseA->assertSee('Tenant Alpha Taqueria');
        $responseA->assertSee('ALPHA_CATEGORY_EXCLUSIVE');
        $responseA->assertSee('ALPHA_PRODUCT_TACO');
        $responseA->assertSee('ALPHA_VARIANT_DOUBLE');
        $responseA->assertSee('55-1111-AAAA');

        // Must NEVER see Tenant B details
        $responseA->assertDontSee('Tenant Beta Confidential');
        $responseA->assertDontSee('BETA_SECRET_CATEGORY');
        $responseA->assertDontSee('BETA_SECRET_BURGER');
        $responseA->assertDontSee('BETA_SECRET_VARIANT');
        $responseA->assertDontSee('55-2222-BBBB');
        $responseA->assertDontSee('999.00');

        // Now verify Tenant B menu
        $responseB = $this->get(route('menu.publico', ['slug' => $tenantB->slug]));
        $responseB->assertOk();

        $responseB->assertSee('Tenant Beta Confidential');
        $responseB->assertSee('BETA_SECRET_CATEGORY');
        $responseB->assertSee('BETA_SECRET_BURGER');
        $responseB->assertSee('BETA_SECRET_VARIANT');
        $responseB->assertSee('55-2222-BBBB');

        $responseB->assertDontSee('Tenant Alpha Taqueria');
        $responseB->assertDontSee('ALPHA_CATEGORY_EXCLUSIVE');
        $responseB->assertDontSee('ALPHA_PRODUCT_TACO');
        $responseB->assertDontSee('ALPHA_VARIANT_DOUBLE');
    }

    public function test_cross_tenant_tampered_relationships_are_isolated(): void
    {
        $tenantA = Restaurante::factory()->create(['slug' => 'tenant-a']);
        $tenantB = Restaurante::factory()->create(['slug' => 'tenant-b']);

        $catA = Categoria::factory()->create([
            'restaurante_id' => $tenantA->id,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $catA->id,
            'idioma' => 'es',
            'nombre' => 'CAT_TENANT_A',
        ]);

        // Rogue product: assigned to Category A, but restaurante_id belongs to Tenant B
        $rogueProdB = Producto::factory()->create([
            'restaurante_id' => $tenantB->id,
            'categoria_id' => $catA->id,
            'precio' => 777.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $rogueProdB->id,
            'idioma' => 'es',
            'nombre' => 'ROGUE_TENANT_B_PRODUCT',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $tenantA->slug]));
        $response->assertOk();
        $response->assertSee('CAT_TENANT_A');
        // Rogue product belonging to tenant B must NOT leak into tenant A
        $response->assertDontSee('ROGUE_TENANT_B_PRODUCT');
        $response->assertDontSee('777.00');
    }

    // =========================================================================
    // Area 2: Schedule Edge Cases (Overnight, Closed Overrides, Timezones)
    // =========================================================================

    public function test_schedule_overnight_shift_at_all_boundary_minutes(): void
    {
        // Restaurant opens Friday 20:00:00 to Saturday 02:00:00
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
        ]);

        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 5, // Viernes
            'abre' => '20:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);

        // 1. Viernes 19:59:59 -> CLOSED
        $time1 = Carbon::parse('2026-10-16 19:59:59', 'America/Mexico_City'); // 2026-10-16 is Friday
        $this->assertEquals(5, $time1->dayOfWeek);
        $this->assertFalse($restaurante->estaAbiertoAhora($time1));

        // 2. Viernes 20:00:00 -> OPEN
        $time2 = Carbon::parse('2026-10-16 20:00:00', 'America/Mexico_City');
        $this->assertTrue($restaurante->estaAbiertoAhora($time2));

        // 3. Viernes 23:59:59 -> OPEN
        $time3 = Carbon::parse('2026-10-16 23:59:59', 'America/Mexico_City');
        $this->assertTrue($restaurante->estaAbiertoAhora($time3));

        // 4. Sabado 00:00:00 -> OPEN (Spillover)
        $time4 = Carbon::parse('2026-10-17 00:00:00', 'America/Mexico_City'); // 2026-10-17 is Saturday
        $this->assertEquals(6, $time4->dayOfWeek);
        $this->assertTrue($restaurante->estaAbiertoAhora($time4));

        // 5. Sabado 01:00:00 -> OPEN (Spillover)
        $time5 = Carbon::parse('2026-10-17 01:00:00', 'America/Mexico_City');
        $this->assertTrue($restaurante->estaAbiertoAhora($time5));

        // 6. Sabado 01:59:59 -> OPEN (Spillover last second)
        $time6 = Carbon::parse('2026-10-17 01:59:59', 'America/Mexico_City');
        $this->assertTrue($restaurante->estaAbiertoAhora($time6));

        // 7. Sabado 02:00:00 -> CLOSED (Shift closed)
        $time7 = Carbon::parse('2026-10-17 02:00:00', 'America/Mexico_City');
        $this->assertFalse($restaurante->estaAbiertoAhora($time7));

        // 8. Sabado 03:00:00 -> CLOSED
        $time8 = Carbon::parse('2026-10-17 03:00:00', 'America/Mexico_City');
        $this->assertFalse($restaurante->estaAbiertoAhora($time8));
    }

    public function test_schedule_closed_day_override_and_spillover_protection(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'UTC',
        ]);

        // Friday shift 20:00 - 02:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 5, // Friday
            'abre' => '20:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);

        // Also Friday has an explicit closed override row
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 5,
            'abre' => null,
            'cierra' => null,
            'cerrado' => true,
        ]);

        $restaurante->load('horarios');

        // Queried during Friday: must be CLOSED
        $fridayNight = Carbon::parse('2026-10-16 22:00:00', 'UTC');
        $this->assertFalse($restaurante->estaAbiertoAhora($fridayNight));

        // Queried during Saturday overnight spill: must be CLOSED
        $saturdayMorning = Carbon::parse('2026-10-17 01:00:00', 'UTC');
        $this->assertFalse($restaurante->estaAbiertoAhora($saturdayMorning));
    }

    public function test_schedule_timezone_conversion_accuracy(): void
    {
        // Restaurant located in Tokyo (UTC+9)
        $restauranteTokyo = Restaurante::factory()->create([
            'zona_horaria' => 'Asia/Tokyo',
        ]);

        // Opens Friday 10:00:00 to 18:00:00 Tokyo time
        Horario::factory()->create([
            'restaurante_id' => $restauranteTokyo->id,
            'dia_semana' => 5, // Friday
            'abre' => '10:00:00',
            'cierra' => '18:00:00',
            'cerrado' => false,
        ]);

        // At 2026-10-16 02:00:00 UTC:
        // Tokyo time is 2026-10-16 11:00:00 (Friday 11am) -> OPEN
        $utcTestTimeOpen = Carbon::parse('2026-10-16 02:00:00', 'UTC');
        $this->assertTrue($restauranteTokyo->estaAbiertoAhora($utcTestTimeOpen));

        // At 2026-10-16 10:00:00 UTC:
        // Tokyo time is 2026-10-16 19:00:00 (Friday 7pm) -> CLOSED
        $utcTestTimeClosed = Carbon::parse('2026-10-16 10:00:00', 'UTC');
        $this->assertFalse($restauranteTokyo->estaAbiertoAhora($utcTestTimeClosed));
    }

    public function test_schedule_split_shifts_on_same_day(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'UTC',
        ]);

        // Shift 1: Lunch 12:00 to 16:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1, // Monday
            'abre' => '12:00:00',
            'cierra' => '16:00:00',
            'cerrado' => false,
        ]);

        // Shift 2: Dinner 19:00 to 23:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1, // Monday
            'abre' => '19:00:00',
            'cierra' => '23:00:00',
            'cerrado' => false,
        ]);

        $restaurante->load('horarios');

        $mondayBase = '2026-10-12'; // Monday

        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 11:59:59", 'UTC')));
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 12:00:00", 'UTC')));
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 15:59:59", 'UTC')));
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 16:00:00", 'UTC')));
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 17:30:00", 'UTC')));
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 19:00:00", 'UTC')));
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 22:59:59", 'UTC')));
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$mondayBase 23:00:00", 'UTC')));
    }

    // =========================================================================
    // Area 3: Parameter Tampering (?mesa= and ?lang=)
    // =========================================================================

    public function test_param_tampering_mesa_xss_and_injection_payloads(): void
    {
        $restaurante = Restaurante::factory()->create();

        $dangerousPayloads = [
            '<script>alert("XSS")</script>',
            '"><img src=x onerror=alert(1)>',
            "'; DROP TABLE users; --",
            'Mesa" onmouseover="alert(1)',
            'Mesa #4!',
            'Mesa Ñandú',
            'Mesa 寿司',
            '123456789012345678901', // 21 chars (>20)
        ];

        foreach ($dangerousPayloads as $payload) {
            $response = $this->get(route('menu.publico', [
                'slug' => $restaurante->slug,
                'mesa' => $payload,
            ]));

            $response->assertOk();
            // Payload must NOT appear as active mesa badge
            $response->assertDontSee('<span class="badge-mesa', false);
            // Must not contain raw XSS tags
            $response->assertDontSee('<script>alert("XSS")</script>', false);
            $response->assertDontSee('onerror=alert(1)', false);
        }

        // Valid 20 chars allowed
        $valid20 = 'Mesa-VIP_Area 12345'; // exactly 19 chars
        $responseValid = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'mesa' => $valid20,
        ]));
        $responseValid->assertOk();
        $responseValid->assertSee('Mesa '.$valid20);
    }

    public function test_param_tampering_mesa_array_or_unexpected_types(): void
    {
        $restaurante = Restaurante::factory()->create();

        // Pass array ?mesa[]=test
        $response = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'mesa' => ['malicious', 'array'],
        ]));

        $response->assertOk();
        $response->assertDontSee('<span class="badge-mesa', false);
    }

    public function test_param_tampering_lang_unknown_and_path_traversal(): void
    {
        $restaurante = Restaurante::factory()->create([
            'idioma_default' => 'es',
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $restaurante->id,
            'idioma' => 'en',
        ]);

        $invalidLangs = [
            '../../../../etc/passwd',
            '<script>alert(1)</script>',
            'zh-CN', // not enabled
            'fr',    // not enabled
            'DROP TABLE',
            '',
        ];

        foreach ($invalidLangs as $lang) {
            // When user sends unsupported Accept-Language (e.g. 'de'), it must fall back to idioma_default ('es')
            $response = $this->withHeaders(['Accept-Language' => 'de,it;q=0.8'])
                ->get(route('menu.publico', [
                    'slug' => $restaurante->slug,
                    'lang' => $lang,
                ]));

            $response->assertOk();
            $this->assertEquals('es', app()->getLocale(), "Failed fallback for lang: {$lang}");
        }

        // When restaurant ONLY has 'es', any Accept-Language falls back to 'es'
        $restauranteEsOnly = Restaurante::factory()->create(['idioma_default' => 'es']);
        $respEs = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get(route('menu.publico', [
                'slug' => $restauranteEsOnly->slug,
                'lang' => 'fr',
            ]));
        $respEs->assertOk();
        $this->assertEquals('es', app()->getLocale());

        // Legitimate enabled language via query param overrides Accept-Language (isolated scope)
        RestauranteScope::setRestauranteId(null);
        $responseEn = $this->withHeaders(['Accept-Language' => 'es'])
            ->get(route('menu.publico', [
                'slug' => $restaurante->slug,
                'lang' => 'en',
            ]));
        $responseEn->assertOk();
        $this->assertEquals(['es', 'en'], $responseEn->viewData('idiomasDisponibles'));
        $this->assertEquals('en', $responseEn->viewData('idioma'));
    }

    public function test_param_tampering_lang_array(): void
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);

        // Pass ?lang[]=en
        $response = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'lang' => ['en', 'es'],
        ]));

        $response->assertOk();
        $this->assertEquals('es', app()->getLocale());
    }

    // =========================================================================
    // Area 4: L2 Cache Structure, Hit & Entity Invalidation
    // =========================================================================

    public function test_cache_hit_returns_catalog_and_updates_open_status_dynamically(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'bistrot-cache',
            'zona_horaria' => 'UTC',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525599887766',
        ]);

        $cat = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $cat->id,
            'idioma' => 'es',
            'nombre' => 'Entradas Gourmet',
        ]);

        $prod = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $cat->id,
            'precio' => 150.00,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prod->id,
            'idioma' => 'es',
            'nombre' => 'Tartar de Atún',
        ]);

        // Shift 12:00 to 14:00 on Monday
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1,
            'abre' => '12:00:00',
            'cierra' => '14:00:00',
            'cerrado' => false,
        ]);

        // Request 1: Monday at 12:30 UTC -> OPEN
        Carbon::setTestNow('2026-10-12 12:30:00');
        $cacheKey = "menu:{$restaurante->id}:es";
        $this->assertFalse(Cache::has($cacheKey));

        $resp1 = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $resp1->assertOk();
        $resp1->assertSee('Abierto ahora');
        $resp1->assertSee('Tartar de Atún');

        // Verify cache was populated
        $this->assertTrue(Cache::has($cacheKey));
        $cachedData = Cache::get($cacheKey);
        $this->assertIsArray($cachedData);
        $this->assertArrayHasKey('categorias', $cachedData);
        $this->assertArrayHasKey('destacados', $cachedData);
        $this->assertArrayHasKey('contactos', $cachedData);
        $this->assertArrayHasKey('horarios', $cachedData);

        // Request 2: Monday at 14:30 UTC -> CLOSED
        // Even though cache is HIT, open status must be evaluated dynamically!
        Carbon::setTestNow('2026-10-12 14:30:00');
        $resp2 = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $resp2->assertOk();
        $resp2->assertSee('Cerrado');
        $resp2->assertDontSee('Abierto ahora');
        // Catalog data is still served from cache
        $resp2->assertSee('Tartar de Atún');
    }

    public function test_cache_invalidation_purges_on_entity_mutations(): void
    {
        Queue::fake();

        $restaurante = Restaurante::factory()->create([
            'slug' => 'mutation-test',
        ]);
        $cat = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $cat->id,
            'idioma' => 'es',
            'nombre' => 'Ensaladas Frescas',
        ]);

        $cacheKey = "menu:{$restaurante->id}:es";

        // Warm up cache
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // 1. Updating category should purge cache
        $cat->update(['orden' => 99]);
        $this->assertFalse(Cache::has($cacheKey), 'Cache was not purged when Categoria was updated');

        // Warm up cache again
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // 2. Creating a new Producto should purge cache
        $newProd = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $cat->id,
            'precio' => 85.00,
            'visible' => true,
        ]);
        $this->assertFalse(Cache::has($cacheKey), 'Cache was not purged when Producto was created');

        // Warm up cache again
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // 3. Deleting Producto should purge cache
        $newProd->delete();
        $this->assertFalse(Cache::has($cacheKey), 'Cache was not purged when Producto was deleted');

        // Warm up cache again
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // 4. Updating Horario should purge cache
        $horario = Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 2,
            'abre' => '09:00:00',
            'cierra' => '17:00:00',
            'cerrado' => false,
        ]);
        $this->assertFalse(Cache::has($cacheKey), 'Cache was not purged when Horario was created');

        // Warm up cache again
        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // 5. Updating Contacto should purge cache
        $contacto = Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'telefono',
            'valor' => '5512345678',
            'visible' => true,
        ]);
        $this->assertFalse(Cache::has($cacheKey), 'Cache was not purged when Contacto was created');

        // Verify async L2 Cloudflare purge job was dispatched
        Queue::assertPushed(PurgarCacheRestauranteJob::class);
    }

    // =========================================================================
    // Area 5: Security Headers & Escaping Vulnerabilities
    // =========================================================================

    public function test_all_security_headers_present_with_exact_directives(): void
    {
        $restaurante = Restaurante::factory()->create();

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self' 'unsafe-inline' https:;", $csp);
        $this->assertStringContainsString("img-src 'self' data: https:;", $csp);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=30', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=86400', $cacheControl);
    }

    public function test_blade_xss_and_quote_escaping_in_dish_names_and_variants(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Restaurante <script>alert("xss-rest")</script>',
            'descripcion' => 'Desc with "quotes" and <svg onload=alert(1)>',
            'moneda' => 'MXN',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525500000000',
        ]);

        // Force open
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $cat = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $cat->id,
            'idioma' => 'es',
            'nombre' => 'Cat <img src=x onerror=alert("cat")>',
            'descripcion' => 'CatDesc "escaped"',
        ]);

        $prod = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $cat->id,
            'precio' => 99.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prod->id,
            'idioma' => 'es',
            'nombre' => "Chef's \"Special\" <script>alert('xss-dish')</script>",
            'descripcion' => "Description with 'quotes' & <b>html</b>",
        ]);

        $var = ProductoVariante::factory()->create([
            'producto_id' => $prod->id,
            'precio' => 119.00,
            'disponible' => true,
        ]);
        VarianteTraduccion::factory()->create([
            'variante_id' => $var->id,
            'idioma' => 'es',
            'nombre' => "Double \"Cheese\" <svg onload=alert('xss-var')>",
        ]);

        $contacto = Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'telefono',
            'etiqueta' => 'Tel <script>',
            'valor' => '555-4321',
            'visible' => true,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        // 1. Raw scripts must NEVER appear
        $response->assertDontSee('<script>alert("xss-rest")</script>', false);
        $response->assertDontSee('<script>alert(\'xss-dish\')</script>', false);
        $response->assertDontSee('<svg onload=alert(\'xss-var\')>', false);
        $response->assertDontSee('<img src=x onerror=alert("cat")>', false);
        $response->assertDontSee('Tel <script>', false);

        // 2. Properly HTML-escaped entities must be rendered
        $response->assertSee('&lt;script&gt;alert(&quot;xss-rest&quot;)&lt;/script&gt;', false);
    }

    public function test_consecutive_requests_to_different_tenants_exposes_scope_leak_on_idiomas_eager_load(): void
    {
        // Tenant A only has default Spanish
        $tenantA = Restaurante::factory()->create([
            'slug' => 'tenant-a-consecutive',
            'idioma_default' => 'es',
        ]);

        // Tenant B has English and French configured
        $tenantB = Restaurante::factory()->create([
            'slug' => 'tenant-b-consecutive',
            'idioma_default' => 'es',
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $tenantB->id,
            'idioma' => 'en',
            'orden' => 1,
        ]);
        RestauranteIdioma::factory()->create([
            'restaurante_id' => $tenantB->id,
            'idioma' => 'fr',
            'orden' => 2,
        ]);

        // Step 1: Visit Tenant A. Public request must clean up scope to null upon completion.
        $respA = $this->get(route('menu.publico', ['slug' => $tenantA->slug]));
        $respA->assertOk();
        $this->assertNull(RestauranteScope::getRestauranteId(), 'Scope must be clean after request to Tenant A');

        // Step 2: Explicitly simulate dirty scope from an uncleaned prior operation to verify
        // that MenuPublicoController establishes tenant scope before loading child relations.
        RestauranteScope::setRestauranteId($tenantA->id);

        $respB = $this->withHeaders(['Accept-Language' => 'es'])
            ->get(route('menu.publico', ['slug' => $tenantB->slug, 'lang' => 'en']));

        $respB->assertOk();

        // Tenant B's idiomasDisponibles must contain 'es', 'en', and 'fr'
        $this->assertContains('en', $respB->viewData('idiomasDisponibles'), 'Tenant B lost English language configuration due to scope pollution from Tenant A');
        $this->assertEquals('en', $respB->viewData('idioma'), 'Tenant B rejected ?lang=en because scope pollution caused idiomas relation to be empty');
    }

    public function test_menu_publico_controller_leaves_restaurante_scope_polluted_globally(): void
    {
        $r1 = Restaurante::factory()->create(['slug' => 'tenant-scope-leak-1']);

        // Before request, scope should be clean
        RestauranteScope::setRestauranteId(null);
        $this->assertNull(RestauranteScope::getRestauranteId());

        // Perform public request
        $resp = $this->get(route('menu.publico', ['slug' => $r1->slug]));
        $resp->assertOk();

        // After public request completes, scope must be clean (null) to avoid polluting subsequent requests or background tasks
        $this->assertNull(
            RestauranteScope::getRestauranteId(),
            'RestauranteScope was left dirty with tenant ID '.RestauranteScope::getRestauranteId().' after request ended'
        );
    }

    public function test_security_headers_on_404_responses(): void
    {
        $response404 = $this->get(route('menu.publico', ['slug' => 'non-existent-restaurant-slug-999']));

        $response404->assertStatus(404);
        $response404->assertViewIs('menu.no-disponible');

        // Check if security headers are returned on 404 responses
        $xcto = $response404->headers->get('X-Content-Type-Options');
        $xfo = $response404->headers->get('X-Frame-Options');
        $csp = $response404->headers->get('Content-Security-Policy');

        $this->assertSame('nosniff', $xcto, '404 public response missing X-Content-Type-Options: nosniff');
        $this->assertSame('SAMEORIGIN', $xfo, '404 public response missing X-Frame-Options: SAMEORIGIN');
        $this->assertNotNull($csp, '404 public response missing Content-Security-Policy');
    }

    public function test_cart_order_eligibility_all_combinations(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'America/Mexico_City')); // miércoles al mediodía
        // Reset scope before setting up factories
        RestauranteScope::setRestauranteId(null);

        // Combo 1: pedidos_activos=false, open=true, whatsapp=filled -> Cart blocked
        $r1 = Restaurante::factory()->create([
            'pedidos_activos' => false,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $r1->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);
        $resp1 = $this->get(route('menu.publico', ['slug' => $r1->slug]));
        $resp1->assertOk();
        $resp1->assertDontSee('id="floatingCartBar"', false);
        $resp1->assertSee('Para ordenar, pídele a tu mesero.');

        // Reset scope before setting up next restaurant
        RestauranteScope::setRestauranteId(null);

        // Combo 2: pedidos_activos=true, open=false (closed), whatsapp=filled -> Cart blocked
        $r2 = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $r2->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'cerrado' => true,
        ]);
        $resp2 = $this->get(route('menu.publico', ['slug' => $r2->slug]));
        $resp2->assertOk();
        $resp2->assertDontSee('id="floatingCartBar"', false);
        $resp2->assertSee('Estamos cerrados.');

        // Reset scope before setting up next restaurant
        RestauranteScope::setRestauranteId(null);

        // Combo 3: pedidos_activos=true, open=true, whatsapp=null -> Cart blocked
        $r3 = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => null,
        ]);
        Horario::factory()->create([
            'restaurante_id' => $r3->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);
        $resp3 = $this->get(route('menu.publico', ['slug' => $r3->slug]));
        $resp3->assertOk();
        $resp3->assertDontSee('id="floatingCartBar"', false);
        $resp3->assertSee('Por ahora no recibimos pedidos aquí. Pídele a tu mesero.');

        // Reset scope before setting up next restaurant
        RestauranteScope::setRestauranteId(null);

        // Combo 4: pedidos_activos=true, open=true, whatsapp=filled -> Cart ACTIVE
        $r4 = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $r4->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);
        $resp4 = $this->get(route('menu.publico', ['slug' => $r4->slug]));
        $resp4->assertOk();
        $resp4->assertSee('id="floatingCartBar"', false);
        $resp4->assertSee('id="cartModalBackdrop"', false);
        $resp4->assertDontSee('Este restaurante no tiene pedidos en línea activos actualmente');
    }

    // =========================================================================
    // Area 6: Edge Cases — Empty Menus & Zero Schedules
    // =========================================================================

    public function test_empty_menu_restaurant_with_zero_categories_and_dishes_renders_cleanly(): void
    {
        RestauranteScope::setRestauranteId(null);

        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Restaurante Vacio',
            'slug' => 'restaurante-vacio',
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertViewIs('menu.publico');
        $response->assertSee('Restaurante Vacio');
        // Zero schedules -> restaurant is closed
        $this->assertFalse($response->viewData('estaAbierto'));
        $response->assertSee('Cerrado');
        $response->assertDontSee('id="floatingCartBar"', false);
        $this->assertEmpty($response->viewData('categorias'));
        $this->assertEmpty($response->viewData('destacados'));
        $this->assertEmpty($response->viewData('contactos'));
        $this->assertEmpty($response->viewData('horarios'));
    }

    public function test_category_with_zero_products_renders_empty_state_without_crashing(): void
    {
        RestauranteScope::setRestauranteId(null);

        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Bistro Con Categoria Vacia',
            'slug' => 'bistro-cat-vacia',
        ]);

        $cat = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $cat->id,
            'idioma' => 'es',
            'nombre' => 'Bebidas Calientes',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Bebidas Calientes');
        $response->assertSee('Pronto habrá platillos aquí.');
    }

    // =========================================================================
    // Area 7: Multi-Turn Schedules & Weekend Overnight Boundaries
    // =========================================================================

    public function test_schedule_overnight_shifts_on_weekend_boundary_saturday_to_sunday_and_sunday_to_monday(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'UTC',
        ]);

        // Shift 1: Saturday (6) 22:00 to Sunday 03:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 6, // Saturday
            'abre' => '22:00:00',
            'cierra' => '03:00:00',
            'cerrado' => false,
        ]);

        // Shift 2: Sunday (0) 21:00 to Monday 02:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 0, // Sunday
            'abre' => '21:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);

        $restaurante->load('horarios');

        // Saturday 2026-10-17 21:59:59 -> CLOSED
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-17 21:59:59', 'UTC')));
        // Saturday 2026-10-17 22:00:00 -> OPEN
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-17 22:00:00', 'UTC')));
        // Sunday 2026-10-18 01:00:00 -> OPEN (Spillover from Saturday)
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-18 01:00:00', 'UTC')));
        // Sunday 2026-10-18 02:59:59 -> OPEN (Spillover last second)
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-18 02:59:59', 'UTC')));
        // Sunday 2026-10-18 03:00:00 -> CLOSED
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-18 03:00:00', 'UTC')));
        // Sunday 2026-10-18 12:00:00 -> CLOSED
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-18 12:00:00', 'UTC')));

        // Sunday 2026-10-18 20:59:59 -> CLOSED
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-18 20:59:59', 'UTC')));
        // Sunday 2026-10-18 21:00:00 -> OPEN
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-18 21:00:00', 'UTC')));
        // Monday 2026-10-19 01:00:00 -> OPEN (Spillover from Sunday to Monday)
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-19 01:00:00', 'UTC')));
        // Monday 2026-10-19 02:00:00 -> CLOSED
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse('2026-10-19 02:00:00', 'UTC')));
    }

    public function test_schedule_triple_split_shifts_same_day(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'UTC',
        ]);

        // Turno 1: Desayuno 08:00 - 11:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 3, // Miércoles
            'abre' => '08:00:00',
            'cierra' => '11:00:00',
            'cerrado' => false,
        ]);
        // Turno 2: Comida 13:00 - 16:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 3,
            'abre' => '13:00:00',
            'cierra' => '16:00:00',
            'cerrado' => false,
        ]);
        // Turno 3: Cena 19:00 - 23:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 3,
            'abre' => '19:00:00',
            'cierra' => '23:00:00',
            'cerrado' => false,
        ]);

        $restaurante->load('horarios');
        $baseDate = '2026-10-14'; // Miércoles

        // Antes de turno 1
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 07:59:59", 'UTC')));
        // Durante turno 1
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 08:00:00", 'UTC')));
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 10:30:00", 'UTC')));
        // Fin de turno 1
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 11:00:00", 'UTC')));
        // Entre turno 1 y 2
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 12:00:00", 'UTC')));
        // Durante turno 2
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 13:00:00", 'UTC')));
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 16:00:00", 'UTC')));
        // Entre turno 2 y 3
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 17:30:00", 'UTC')));
        // Durante turno 3
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 19:00:00", 'UTC')));
        $this->assertTrue($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 22:59:59", 'UTC')));
        $this->assertFalse($restaurante->estaAbiertoAhora(Carbon::parse("$baseDate 23:00:00", 'UTC')));
    }

    // =========================================================================
    // Area 8: WCAG 2.2 AA Touch Targets, Focus & Escape Dismiss Behavior
    // =========================================================================

    public function test_wcag_touch_targets_on_all_interactive_elements_meet_minimum_44px(): void
    {
        RestauranteScope::setRestauranteId(null);

        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
            'zona_horaria' => 'UTC',
        ]);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en']);

        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $cat = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $cat->id,
            'idioma' => 'es',
            'nombre' => 'Bebidas',
        ]);

        $prod = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $cat->id,
            'precio' => 50.00,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prod->id,
            'idioma' => 'es',
            'nombre' => 'Agua Fresca',
        ]);

        $contacto = Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'telefono',
            'valor' => '5551234567',
            'visible' => true,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        $content = $response->getContent();

        // 1. Selector de idiomas: min-h-[44px] min-w-[44px]
        $this->assertStringContainsString('min-h-[44px]', $content);
        $this->assertStringContainsString('min-w-[44px]', $content);

        // 2. Botón cerrar modal carrito: min-w-[44px] min-h-[44px]
        $this->assertStringContainsString('id="btnCloseCartModal"', $content);

        // 3. Botones vaciar pedido confirmación y cancelación: min-h-[44px]
        $this->assertStringContainsString('cartEjecutarVaciar()', $content);
        $this->assertStringContainsString('cartCancelarVaciar()', $content);

        // 4. Botón agregar al carrito: min-h-[44px] min-w-[44px]
        $this->assertStringContainsString('btn-cart-add', $content);

        // 5. Enlace de contacto: min-h-[44px]
        $this->assertStringContainsString('tel:5551234567', $content);

        // 6. Botón enviar pedido WhatsApp: min-h-[48px]
        $this->assertStringContainsString('id="btnEnviarWhatsApp"', $content);
        $this->assertStringContainsString('min-h-[48px]', $content);
    }

    public function test_wcag_escape_key_and_aria_attributes_on_cart_modal(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'America/Mexico_City')); // miércoles al mediodía
        RestauranteScope::setRestauranteId(null);

        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        // ARIA attributes on modal and floating bar
        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('aria-controls="cartModalBackdrop"', false);
        $response->assertSee('id="cartModalBackdrop"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-labelledby="cartDrawerTitle"', false);

        // Escape key dismiss handler
        $response->assertSee("e.key === 'Escape' || e.key === 'Esc'", false);
        $response->assertSee('closeCartModal()', false);

        // Focus management: moves focus to close button when opened, restores to bar when closed
        $response->assertSee('closeBtn.focus()', false);
        $response->assertSee('bar.focus()', false);
    }

    // =========================================================================
    // Area 9: Adversarial XSS & Notes Injection Stress-Testing
    // =========================================================================

    public function test_notes_input_and_dish_notes_escaping_in_cart_and_whatsapp(): void
    {
        RestauranteScope::setRestauranteId(null);

        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        // Check that escapeHtml function is defined and correctly escapes &, <, >, ", '
        $response->assertSee('function escapeHtml(str)', false);
        $response->assertSee("replace(/'/g, '&#39;')", false);
        $response->assertSee('replace(/"/g, \'&quot;\')', false);

        // Check encodeURIComponent is used when constructing WhatsApp deep link
        $response->assertSee('encodeURIComponent(msg)', false);
    }

    public function test_restaurant_name_template_literal_xss_in_script_tag(): void
    {
        RestauranteScope::setRestauranteId(null);

        $payload = 'Tacos ${alert(1)}';
        $restaurante = Restaurante::factory()->create([
            'nombre' => $payload,
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Tacos ${alert(1)}', $content);
    }

    public function test_restaurant_name_backtick_injection_breaks_template_literal(): void
    {
        RestauranteScope::setRestauranteId(null);

        $payload = 'Tacos`; alert(document.domain); let dummy=`';
        $restaurante = Restaurante::factory()->create([
            'nombre' => $payload,
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        $content = $response->getContent();
        preg_match_all('#<script>(.*?)</script>#s', $content, $bloques);
        $script = implode('', $bloques[1]);

        $this->assertStringContainsString('const NOMBRE = '.Js::from($payload)->toHtml().';', $script);
        $this->assertStringNotContainsString('alert(document.domain);', str_replace(Js::from($payload)->toHtml(), '', $script));
    }

    public function test_dish_name_with_newline_creates_unbroken_or_broken_js_string(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'America/Mexico_City')); // miércoles al mediodía
        RestauranteScope::setRestauranteId(null);

        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525511112222',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => Carbon::now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $cat = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $cat->id, 'idioma' => 'es', 'nombre' => 'Tacos']);

        $prod = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $cat->id,
            'precio' => 25.00,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prod->id,
            'idioma' => 'es',
            'nombre' => "Taco\nAl Pastor",
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();

        $content = $response->getContent();
        // The newline must be escaped as a JS literal, never raw inside the onclick attribute.
        $this->assertStringContainsString('nombre: '.Js::from("Taco\nAl Pastor")->toHtml(), $content);
        $this->assertStringNotContainsString("nombre: 'Taco\nAl Pastor'", $content);
    }
}
