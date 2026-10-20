<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuApiStressTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * STRESS TEST 1: Multi-tenant strict isolation under cross-tenant pollution attempts.
     */
    public function test_multi_tenant_isolation_under_adversarial_queries(): void
    {
        $tenantAlpha = Restaurante::factory()->create([
            'nombre' => 'Alpha Bistro',
            'slug' => 'alpha-bistro',
            'moneda' => 'USD',
        ]);

        $catAlpha = Categoria::factory()->create([
            'restaurante_id' => $tenantAlpha->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $catAlpha->id,
            'idioma' => 'es',
            'nombre' => 'Alpha Categoria',
        ]);

        $prodAlpha = Producto::factory()->create([
            'restaurante_id' => $tenantAlpha->id,
            'categoria_id' => $catAlpha->id,
            'precio' => 50.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodAlpha->id,
            'idioma' => 'es',
            'nombre' => 'Alpha Dish Secret',
        ]);

        $tenantBeta = Restaurante::factory()->create([
            'nombre' => 'Beta Grill',
            'slug' => 'beta-grill',
            'moneda' => 'EUR',
        ]);

        $catBeta = Categoria::factory()->create([
            'restaurante_id' => $tenantBeta->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $catBeta->id,
            'idioma' => 'es',
            'nombre' => 'Beta Categoria',
        ]);

        $prodBeta = Producto::factory()->create([
            'restaurante_id' => $tenantBeta->id,
            'categoria_id' => $catBeta->id,
            'precio' => 75.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $prodBeta->id,
            'idioma' => 'es',
            'nombre' => 'Beta Dish Secret',
        ]);

        // Attempting to inject a Beta product linked to Alpha's category
        $rogueProduct = Producto::factory()->create([
            'restaurante_id' => $tenantBeta->id,
            'categoria_id' => $catAlpha->id,
            'precio' => 999.00,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $rogueProduct->id,
            'idioma' => 'es',
            'nombre' => 'Rogue Cross-Tenant Injected Item',
        ]);

        // 1. Request Alpha
        $responseAlpha = $this->getJson(route('api.v1.menu.show', ['slug' => $tenantAlpha->slug]));
        $responseAlpha->assertOk();
        $responseAlpha->assertJsonPath('data.nombre', 'Alpha Bistro');
        $responseAlpha->assertJsonPath('data.moneda', 'USD');
        $responseAlpha->assertSee('Alpha Categoria');
        $responseAlpha->assertSee('Alpha Dish Secret');

        // Confirm zero presence of Beta or rogue injected item
        $responseAlpha->assertDontSee('Beta Bistro');
        $responseAlpha->assertDontSee('Beta Categoria');
        $responseAlpha->assertDontSee('Beta Dish Secret');
        $responseAlpha->assertDontSee('Rogue Cross-Tenant Injected Item');

        // Confirm global scope is cleanly reset
        $this->assertNull(RestauranteScope::getRestauranteId());

        // 2. Request Beta sequentially
        $responseBeta = $this->getJson(route('api.v1.menu.show', ['slug' => $tenantBeta->slug]));
        $responseBeta->assertOk();
        $responseBeta->assertJsonPath('data.nombre', 'Beta Grill');
        $responseBeta->assertJsonPath('data.moneda', 'EUR');
        $responseBeta->assertSee('Beta Categoria');
        $responseBeta->assertSee('Beta Dish Secret');
        $responseBeta->assertDontSee('Alpha Dish Secret');

        $this->assertNull(RestauranteScope::getRestauranteId());
    }

    /**
     * STRESS TEST 2: Inactive, suspended, deleted, or unconfigured restaurant states.
     */
    public function test_inactive_suspended_deleted_and_unconfigured_restaurants(): void
    {
        // 1. Suspended status
        $rSuspendido = Restaurante::factory()->create(['estado' => 'suspendido']);
        $resSuspendido = $this->getJson(route('api.v1.menu.show', ['slug' => $rSuspendido->slug]));
        $resSuspendido->assertNotFound();
        $resSuspendido->assertJson(['message' => 'Restaurante no encontrado o no disponible.']);

        // 2. Soft deleted restaurant
        $rDeleted = Restaurante::factory()->create(['estado' => 'activo']);
        $slugDeleted = $rDeleted->slug;
        $rDeleted->delete();
        $resDeleted = $this->getJson(route('api.v1.menu.show', ['slug' => $slugDeleted]));
        $resDeleted->assertNotFound();
        $resDeleted->assertJson(['message' => 'Restaurante no encontrado o no disponible.']);

        // 3. Non-existent slug
        $resNonExistent = $this->getJson(route('api.v1.menu.show', ['slug' => 'definitely-does-not-exist']));
        $resNonExistent->assertNotFound();
        $resNonExistent->assertJson(['message' => 'Restaurante no encontrado o no disponible.']);

        // 5. Active restaurant with zero opening hours configured
        $rSinHorarios = Restaurante::factory()->create([
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+525511223344',
        ]);
        $resSinHorarios = $this->getJson(route('api.v1.menu.show', ['slug' => $rSinHorarios->slug]));
        $resSinHorarios->assertOk();
        $resSinHorarios->assertJsonPath('data.abierto', false);
        $resSinHorarios->assertJsonPath('data.puede_pedir', false);

        // 6. Active restaurant with day marked closed explicitly
        Carbon::setTestNow(Carbon::parse('2026-10-21 14:00:00', 'America/Mexico_City')); // Wednesday = 3
        $rDiaCerrado = Restaurante::factory()->create([
            'estado' => 'activo',
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+525511223344',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $rDiaCerrado->id,
            'dia_semana' => 3, // Wednesday
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => true, // Flagged closed
        ]);
        $resDiaCerrado = $this->getJson(route('api.v1.menu.show', ['slug' => $rDiaCerrado->slug]));
        $resDiaCerrado->assertOk();
        $resDiaCerrado->assertJsonPath('data.abierto', false);
        $resDiaCerrado->assertJsonPath('data.puede_pedir', false);
    }

    /**
     * STRESS TEST 3: Overnight shifts, boundary conditions, and week-boundary rollover (Sunday to Monday).
     */
    public function test_overnight_shifts_boundaries_and_sunday_to_monday_rollover(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+525512345678',
        ]);

        // Shift 1: Friday night (5) 21:00 to Saturday 03:00 (abre > cierra)
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 5, // Friday
            'abre' => '21:00:00',
            'cierra' => '03:00:00',
            'cerrado' => false,
        ]);

        // Shift 2: Sunday night (0) 20:00 to Monday 04:00 (abre > cierra, Sunday to Monday week boundary)
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 0, // Sunday
            'abre' => '20:00:00',
            'cierra' => '04:00:00',
            'cerrado' => false,
        ]);

        // Case A: Friday 20:59:59 (1 second before opening) -> Closed
        Carbon::setTestNow(Carbon::parse('2026-10-23 20:59:59', 'America/Mexico_City')); // Friday
        $resA = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resA->assertJsonPath('data.abierto', false);

        // Case B: Friday 21:00:00 (Exact opening time) -> Open
        Carbon::setTestNow(Carbon::parse('2026-10-23 21:00:00', 'America/Mexico_City'));
        $resB = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resB->assertJsonPath('data.abierto', true);
        $resB->assertJsonPath('data.puede_pedir', true);

        // Case C: Friday 23:59:59 (Late Friday) -> Open
        Carbon::setTestNow(Carbon::parse('2026-10-23 23:59:59', 'America/Mexico_City'));
        $resC = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resC->assertJsonPath('data.abierto', true);

        // Case D: Saturday 00:00:00 (Midnight rollover) -> Open
        Carbon::setTestNow(Carbon::parse('2026-10-24 00:00:00', 'America/Mexico_City')); // Saturday
        $resD = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resD->assertJsonPath('data.abierto', true);

        // Case E: Saturday 02:59:59 (1 second before shift end) -> Open
        Carbon::setTestNow(Carbon::parse('2026-10-24 02:59:59', 'America/Mexico_City'));
        $resE = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resE->assertJsonPath('data.abierto', true);

        // Case F: Saturday 03:00:00 (Exact shift end) -> Closed
        Carbon::setTestNow(Carbon::parse('2026-10-24 03:00:00', 'America/Mexico_City'));
        $resF = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resF->assertJsonPath('data.abierto', false);

        // Case G: Week rollover: Sunday night into Monday morning at 02:30 AM
        // 2026-10-26 is Monday. Sunday night was 2026-10-25.
        Carbon::setTestNow(Carbon::parse('2026-10-26 02:30:00', 'America/Mexico_City'));
        $resG = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resG->assertJsonPath('data.abierto', true);
        $resG->assertJsonPath('data.puede_pedir', true);

        // Case H: Monday 04:00:00 -> Shift ended, now closed
        Carbon::setTestNow(Carbon::parse('2026-10-26 04:00:00', 'America/Mexico_City'));
        $resH = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $resH->assertJsonPath('data.abierto', false);
    }

    /**
     * STRESS TEST 4: Multiple split shifts in the same calendar day.
     */
    public function test_multiple_split_shifts_on_same_day(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
        ]);

        // Lunch: Tuesday 12:00 to 16:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 2, // Tuesday
            'abre' => '12:00:00',
            'cierra' => '16:00:00',
            'cerrado' => false,
        ]);

        // Dinner: Tuesday 19:30 to 23:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 2, // Tuesday
            'abre' => '19:30:00',
            'cierra' => '23:00:00',
            'cerrado' => false,
        ]);

        // 11:30 AM -> Closed
        Carbon::setTestNow(Carbon::parse('2026-10-20 11:30:00', 'America/Mexico_City'));
        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))
            ->assertJsonPath('data.abierto', false);

        // 13:00 PM -> Open (Lunch)
        Carbon::setTestNow(Carbon::parse('2026-10-20 13:00:00', 'America/Mexico_City'));
        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))
            ->assertJsonPath('data.abierto', true);

        // 17:30 PM -> Closed (Siesta / Break between shifts)
        Carbon::setTestNow(Carbon::parse('2026-10-20 17:30:00', 'America/Mexico_City'));
        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))
            ->assertJsonPath('data.abierto', false);

        // 20:00 PM -> Open (Dinner)
        Carbon::setTestNow(Carbon::parse('2026-10-20 20:00:00', 'America/Mexico_City'));
        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))
            ->assertJsonPath('data.abierto', true);

        // 23:30 PM -> Closed
        Carbon::setTestNow(Carbon::parse('2026-10-20 23:30:00', 'America/Mexico_City'));
        $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]))
            ->assertJsonPath('data.abierto', false);
    }

    /**
     * STRESS TEST 5: CORS preflight headers and origin restriction.
     */
    public function test_cors_preflight_and_unauthorized_origin_handling(): void
    {
        $restaurante = Restaurante::factory()->create();

        // 1. Allowed origin localhost:3000 OPTIONS preflight
        $responseAllowed = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Accept, Content-Type, Authorization',
        ])->options('/api/v1/menu/'.$restaurante->slug);

        $this->assertContains($responseAllowed->getStatusCode(), [200, 204]);
        $responseAllowed->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->assertTrue($responseAllowed->headers->has('Access-Control-Allow-Methods'));

        // 2. Allowed origin 127.0.0.1:3000
        $responseIp = $this->withHeaders([
            'Origin' => 'http://127.0.0.1:3000',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/menu/'.$restaurante->slug);

        $this->assertContains($responseIp->getStatusCode(), [200, 204]);
        $responseIp->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:3000');

        // 3. Unauthorized origin: should NOT receive Access-Control-Allow-Origin for that evil domain
        $responseEvil = $this->withHeaders([
            'Origin' => 'https://malicious-phishing-site.com',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/menu/'.$restaurante->slug);

        $allowOrigin = $responseEvil->headers->get('Access-Control-Allow-Origin');
        $this->assertNotEquals('https://malicious-phishing-site.com', $allowOrigin);
    }

    /**
     * STRESS TEST 6: Monogram generation with special characters, unicode, spaces, and emojis.
     */
    public function test_monogram_fallback_special_characters_and_unicodes(): void
    {
        // 1. Leading spaces + accent: "   Árbol Café   "
        $r1 = Restaurante::factory()->create(['nombre' => '   Árbol Café   ', 'logo' => null]);
        $res1 = $this->getJson(route('api.v1.menu.show', ['slug' => $r1->slug]));
        $res1->assertJsonPath('data.monograma', 'Á');

        // 2. Multibyte Ñ: "ñoquis caseros"
        $r2 = Restaurante::factory()->create(['nombre' => 'ñoquis caseros', 'logo' => null]);
        $res2 = $this->getJson(route('api.v1.menu.show', ['slug' => $r2->slug]));
        $res2->assertJsonPath('data.monograma', 'Ñ');

        // 3. Numbers: "100 Montaditos"
        $r3 = Restaurante::factory()->create(['nombre' => '100 Montaditos', 'logo' => null]);
        $res3 = $this->getJson(route('api.v1.menu.show', ['slug' => $r3->slug]));
        $res3->assertJsonPath('data.monograma', '1');

        // 4. Emoji: "🍕 Pizza Mania"
        $r4 = Restaurante::factory()->create(['nombre' => '🍕 Pizza Mania', 'logo' => null]);
        $res4 = $this->getJson(route('api.v1.menu.show', ['slug' => $r4->slug]));
        $res4->assertJsonPath('data.monograma', '🍕');

        // 5. Special quotes and punctuation: '"El Rincón"'
        $r5 = Restaurante::factory()->create(['nombre' => '"El Rincón"', 'logo' => null]);
        $res5 = $this->getJson(route('api.v1.menu.show', ['slug' => $r5->slug]));
        $res5->assertJsonPath('data.monograma', '"');
    }

    /**
     * STRESS TEST 7: Table (mesa) parameter sanitization under adversarial inputs.
     */
    public function test_table_parameter_sanitization_under_adversarial_inputs(): void
    {
        $restaurante = Restaurante::factory()->create();

        // 1. Valid alphanumeric with space and hyphen
        $res1 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => 'Mesa-12 B']));
        $res1->assertJsonPath('data.mesa', 'Mesa-12 B');

        // 2. Valid with underscore
        $res2 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => 'Terraza_5']));
        $res2->assertJsonPath('data.mesa', 'Terraza_5');

        // 3. Leading and trailing whitespace should be trimmed
        $res3 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => '   Bar-01   ']));
        $res3->assertJsonPath('data.mesa', 'Bar-01');

        // 4. Over 20 characters: Rejected -> null
        $res4 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => 'Mesa123456789012345678901']));
        $res4->assertJsonPath('data.mesa', null);

        // 5. XSS attempt
        $res5 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => '<script>alert(1)</script>']));
        $res5->assertJsonPath('data.mesa', null);

        // 6. SQL injection attempt
        $res6 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => "1' OR '1'='1"]));
        $res6->assertJsonPath('data.mesa', null);

        // 7. Dot, semicolon, hash special characters
        $res7 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => 'Mesa#1;DROP']));
        $res7->assertJsonPath('data.mesa', null);

        // 8. Empty string or only spaces
        $res8 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => '   ']));
        $res8->assertJsonPath('data.mesa', null);
    }

    /**
     * STRESS TEST 8: Discount calculation edge cases (offer >= regular, 0 price, identical price).
     */
    public function test_discount_calculation_edge_cases(): void
    {
        $restaurante = Restaurante::factory()->create(['moneda' => 'MXN']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);

        // Case A: Real discount (offer < regular)
        $pReal = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 150.00,
            'precio_oferta' => 120.00,
            'orden' => 1,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $pReal->id, 'idioma' => 'es', 'nombre' => 'Real Discount']);

        // Case B: Offer price EXACTLY EQUAL to regular price (must NOT be treated as offer)
        $pEqual = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 150.00,
            'precio_oferta' => 150.00,
            'orden' => 2,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $pEqual->id, 'idioma' => 'es', 'nombre' => 'Equal Price']);

        // Case C: Offer price GREATER than regular price (must NOT be treated as offer)
        $pGreater = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 150.00,
            'precio_oferta' => 200.00,
            'orden' => 3,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $pGreater->id, 'idioma' => 'es', 'nombre' => 'Greater Offer Price']);

        // Case D: Free product ($0.00 regular price)
        $pFree = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 0.00,
            'precio_oferta' => null,
            'orden' => 4,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $pFree->id, 'idioma' => 'es', 'nombre' => 'Free Tap Water']);

        // Case E: Free offer on a paid product (offer = 0.00, regular = 50.00)
        $pFreeOffer = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 50.00,
            'precio_oferta' => 0.00,
            'orden' => 5,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $pFreeOffer->id, 'idioma' => 'es', 'nombre' => 'Complimentary Promotion']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));
        $response->assertOk();

        $platillos = $response->json('data.categorias.0.platillos');

        // Verify Case A (Real discount)
        $this->assertEquals(150.00, $platillos[0]['precio']);
        $this->assertEquals(120.00, $platillos[0]['precio_oferta']);
        $this->assertEquals(120.00, $platillos[0]['precio_vigente']);
        $this->assertTrue($platillos[0]['tiene_oferta']);

        // Verify Case B (Equal price: NOT an offer)
        $this->assertEquals(150.00, $platillos[1]['precio']);
        $this->assertNull($platillos[1]['precio_oferta']);
        $this->assertEquals(150.00, $platillos[1]['precio_vigente']);
        $this->assertFalse($platillos[1]['tiene_oferta']);

        // Verify Case C (Greater price: NOT an offer)
        $this->assertEquals(150.00, $platillos[2]['precio']);
        $this->assertNull($platillos[2]['precio_oferta']);
        $this->assertEquals(150.00, $platillos[2]['precio_vigente']);
        $this->assertFalse($platillos[2]['tiene_oferta']);

        // Verify Case D (Free product: $0.00)
        $this->assertEquals(0.00, $platillos[3]['precio']);
        $this->assertNull($platillos[3]['precio_oferta']);
        $this->assertEquals(0.00, $platillos[3]['precio_vigente']);
        $this->assertFalse($platillos[3]['tiene_oferta']);
        $this->assertStringContainsString('0.00', $platillos[3]['precio_vigente_formateado']);

        // Verify Case E (Free offer: $0.00 offer)
        $this->assertEquals(50.00, $platillos[4]['precio']);
        $this->assertEquals(0.00, $platillos[4]['precio_oferta']);
        $this->assertEquals(0.00, $platillos[4]['precio_vigente']);
        $this->assertTrue($platillos[4]['tiene_oferta']);
        $this->assertStringContainsString('0.00', $platillos[4]['precio_vigente_formateado']);
    }

    /**
     * STRESS TEST 9: Empty translation fallbacks and custom dietary tag codes.
     */
    public function test_untranslated_and_empty_translation_fallbacks(): void
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);

        // Category with NO translations
        $categoria = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        // Product with NO translations
        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 20.00,
            'visible' => true,
            'disponible' => true,
        ]);

        // Custom dietary tag code not in standard catalog
        $customTag = Etiqueta::firstOrCreate(['codigo' => 'sin_azucar_anadido', 'icono' => 'leaf']);
        $producto->etiquetas()->attach($customTag->id);

        // Variant with NO translations
        $variante = ProductoVariante::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 25.00,
            'disponible' => true,
        ]);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'lang' => 'en']));
        $response->assertOk();

        // Defaults should be gracefully rendered without null pointer errors
        $response->assertJsonPath('data.categorias.0.nombre', 'Categoría');
        $response->assertJsonPath('data.categorias.0.platillos.0.nombre', 'Platillo');
        $response->assertJsonPath('data.categorias.0.platillos.0.variantes.0.nombre', 'Opción');
        $response->assertJsonPath('data.categorias.0.platillos.0.etiquetas.0.nombre', 'Sin azucar anadido');
    }

    /**
     * STRESS TEST 10: Accent color sanitization and readable text luminance computation.
     */
    public function test_color_acento_validation_and_luminance_contrast(): void
    {
        // 1. Valid dark accent -> text color should be white (#ffffff)
        $rDark = Restaurante::factory()->create(['color_acento' => '#1a1a1a']);
        $resDark = $this->getJson(route('api.v1.menu.show', ['slug' => $rDark->slug]));
        $resDark->assertJsonPath('data.color_acento', '#1a1a1a');
        $resDark->assertJsonPath('data.color_acento_texto', '#ffffff');

        // 2. Valid bright accent -> text color should be dark (#17171e)
        $rLight = Restaurante::factory()->create(['color_acento' => '#ffff00']);
        $resLight = $this->getJson(route('api.v1.menu.show', ['slug' => $rLight->slug]));
        $resLight->assertJsonPath('data.color_acento', '#ffff00');
        $resLight->assertJsonPath('data.color_acento_texto', '#17171e');

        // 3. Malformed/malicious accent color -> sanitized to null
        // 'red;x:y' mide 7 caracteres porque color_acento es string(7); MariaDB rechaza valores más largos.
        $rMalicious = Restaurante::factory()->create(['color_acento' => 'red;x:y']);
        $resMalicious = $this->getJson(route('api.v1.menu.show', ['slug' => $rMalicious->slug]));
        $resMalicious->assertJsonPath('data.color_acento', null);
        $resMalicious->assertJsonPath('data.color_acento_texto', null);
    }

    /**
     * STRESS TEST 11: Rate limiter menu-publico configuration on the route.
     */
    public function test_rate_limiter_menu_publico_middleware_configuration(): void
    {
        $route = app('router')->getRoutes()->getByName('api.v1.menu.show');

        $this->assertNotNull($route, 'Route api.v1.menu.show must be registered');
        $middleware = $route->gatherMiddleware();

        $this->assertTrue(
            collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:menu-publico')),
            'Route must use throttle:menu-publico middleware'
        );
    }
}
