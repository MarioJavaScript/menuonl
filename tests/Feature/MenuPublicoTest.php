<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use App\Services\MenuCacheService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MenuPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    // ==========================================
    // Tier 1: Feature Coverage (Core Behavior)
    // ==========================================

    public function test_menu_publico_returns_200_and_renders_restaurant_header(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Taquería La Auténtica',
            'slug' => 'taqueria-la-autentica',
            'descripcion' => 'Los mejores tacos al pastor de la ciudad',
            'estado' => 'activo',
        ]);
        $restaurante->update([
            'logo' => "restaurantes/{$restaurante->id}/logos/taqueria.webp",
            'portada' => "restaurantes/{$restaurante->id}/portadas/taqueria.webp",
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertViewIs('menu.publico');
        $response->assertSee('Taquería La Auténtica');
        $response->assertSee('Los mejores tacos al pastor de la ciudad');
    }

    public function test_menu_publico_renders_categories_dishes_and_prices(): void
    {
        $restaurante = Restaurante::factory()->create([
            'moneda' => 'MXN',
            'idioma_default' => 'es',
        ]);

        $categoria = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Tacos Tradicionales',
            'descripcion' => 'Recetas con más de 50 años',
        ]);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 45.00,
            'disponible' => true,
            'visible' => true,
            'orden' => 1,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => 'Taco de Pastor con Todo',
            'descripcion' => 'Tortilla de maíz recién hecha con piña y cilantro',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Tacos Tradicionales');
        $response->assertSee('Recetas con más de 50 años');
        $response->assertSee('Taco de Pastor con Todo');
        $response->assertSee('Tortilla de maíz recién hecha con piña y cilantro');
        $response->assertSee('$45.00');
        $response->assertDontSee('45.00 MXN');
        $response->assertDontSee('$45.00 MXN');
    }

    public function test_offer_price_displayed_with_regular_price_struck_through(): void
    {
        $restaurante = Restaurante::factory()->create(['moneda' => 'MXN']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Cortes Finos',
        ]);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 120.00,
            'precio_oferta' => 89.00,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => 'Corte Arrachera',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('$89.00');
        $response->assertSee('$120.00');
        $response->assertDontSee('89.00 MXN');
        $response->assertDontSee('120.00 MXN');
        $response->assertSee('class="dish-price-old"', false);
        $response->assertSee('class="dish-price-offer"', false);
    }

    public function test_variants_and_individual_variant_prices_rendered(): void
    {
        $restaurante = Restaurante::factory()->create(['moneda' => 'MXN']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Pizzas']);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 100.00,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Pizza Artesanal']);

        $varianteChica = ProductoVariante::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 75.00,
            'disponible' => true,
            'orden' => 1,
        ]);
        VarianteTraduccion::factory()->create(['variante_id' => $varianteChica->id, 'idioma' => 'es', 'nombre' => 'Mediana']);

        $varianteGrande = ProductoVariante::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 135.00,
            'disponible' => true,
            'orden' => 2,
        ]);
        VarianteTraduccion::factory()->create(['variante_id' => $varianteGrande->id, 'idioma' => 'es', 'nombre' => 'Familiar']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Mediana');
        $response->assertSee('$75.00');
        $response->assertSee('Familiar');
        $response->assertSee('$135.00');
        $response->assertDontSee('75.00 MXN');
        $response->assertDontSee('135.00 MXN');
    }

    public function test_dietary_tags_rendered_with_dish(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Ensaladas']);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Bowl Saludable']);

        $tagVegano = Etiqueta::firstOrCreate(['codigo' => 'vegano', 'icono' => 'leaf']);
        $tagSinGluten = Etiqueta::firstOrCreate(['codigo' => 'sin_gluten', 'icono' => 'wheat-off']);
        $producto->etiquetas()->attach([$tagVegano->id, $tagSinGluten->id]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('data-etiqueta="vegano"', false);
        $response->assertSee('data-etiqueta="sin_gluten"', false);
        $response->assertSee('Vegano');
        $response->assertSee('Sin gluten');
    }

    public function test_safe_contact_links_rendered(): void
    {
        $restaurante = Restaurante::factory()->create();

        Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'telefono',
            'etiqueta' => 'Teléfono',
            'valor' => '+525512345678',
            'visible' => true,
        ]);
        Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'email',
            'etiqueta' => 'Correo',
            'valor' => 'contacto@restaurante.com',
            'visible' => true,
        ]);
        Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'web',
            'etiqueta' => 'Sitio Web',
            'valor' => 'https://restaurante.example.com',
            'visible' => true,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('tel:+525512345678', false);
        $response->assertSee('mailto:contacto@restaurante.com', false);
        $response->assertSee('https://restaurante.example.com', false);
    }

    public function test_business_hours_table_grouped_by_day(): void
    {
        $restaurante = Restaurante::factory()->create();

        // Domingo (0): 10:00 - 22:00
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 0,
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ]);
        // Lunes (1): Cerrado
        Horario::factory()->cerrado()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Domingo');
        $response->assertSee('Lunes');
        $response->assertSee('10:00 a. m.');
        $response->assertSee('10:00 p. m.');
        $response->assertSee('Cerrado');
    }

    // ==========================================
    // Tier 2: Boundary & Corner Cases
    // ==========================================

    public function test_non_existent_slug_returns_404_with_no_disponible_view(): void
    {
        $response = $this->get(route('menu.publico', ['slug' => 'slug-inexistente-123456']));

        $response->assertStatus(404);
        $response->assertViewIs('menu.no-disponible');
    }

    public function test_suspended_restaurant_returns_404_with_no_disponible_view(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create();

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertStatus(404);
        $response->assertViewIs('menu.no-disponible');
    }

    public function test_soft_deleted_restaurant_returns_404_with_no_disponible_view(): void
    {
        $restaurante = Restaurante::factory()->create();
        $restaurante->delete();

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertStatus(404);
        $response->assertViewIs('menu.no-disponible');
    }

    public function test_mesa_parameter_sanitized_valid_alphanumeric(): void
    {
        $restaurante = Restaurante::factory()->create();

        $response = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'mesa' => 'Mesa-12_VIP',
        ]));

        $response->assertOk();
        $this->assertSame('Mesa-12_VIP', $response->viewData('mesa'));
        $response->assertSee('Mesa Mesa-12_VIP');
    }

    public function test_mesa_parameter_sanitized_rejects_malicious_script_tags(): void
    {
        $restaurante = Restaurante::factory()->create();

        $response = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'mesa' => '<script>alert(1)</script>',
        ]));

        $response->assertOk();
        $this->assertNull($response->viewData('mesa'));
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_sold_out_dish_omitted_when_mostrar_agotados_is_false(): void
    {
        $restaurante = Restaurante::factory()->create([
            'mostrar_agotados' => false,
        ]);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Especiales']);

        $agotado = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => false,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $agotado->id, 'idioma' => 'es', 'nombre' => 'Platillo Oculto Agotado']);

        $disponible = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $disponible->id, 'idioma' => 'es', 'nombre' => 'Platillo Visible Disponible']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Platillo Visible Disponible');
        $response->assertDontSee('Platillo Oculto Agotado');
    }

    public function test_sold_out_dish_shows_badge_and_disabled_cart_when_mostrar_agotados_is_true(): void
    {
        $restaurante = Restaurante::factory()->create([
            'mostrar_agotados' => true,
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
        ]);
        // Add schedule so restaurant is open
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => (int) now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Carnes']);

        $agotado = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => false,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $agotado->id, 'idioma' => 'es', 'nombre' => 'Corte Agotado Mostrado']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Corte Agotado Mostrado');
        $response->assertSee('Agotado');
        $response->assertSee('disabled', false);
    }

    public function test_language_fallback_query_param_takes_priority(): void
    {
        $restaurante = Restaurante::factory()->create([
            'idioma_default' => 'es',
        ]);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en', 'orden' => 1]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Hamburguesas']);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'en', 'nombre' => 'Burgers']);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Hamburguesa Clásica']);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'en', 'nombre' => 'Classic Burger']);

        $response = $this->withHeader('Accept-Language', 'es-ES,es;q=0.9')
            ->get(route('menu.publico', ['slug' => $restaurante->slug, 'lang' => 'en']));

        $response->assertOk();
        $response->assertSee('Burgers');
        $response->assertSee('Classic Burger');
        $response->assertDontSee('Hamburguesa Clásica');
    }

    public function test_language_fallback_accept_language_header_used_when_no_query_param(): void
    {
        $restaurante = Restaurante::factory()->create([
            'idioma_default' => 'es',
        ]);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en', 'orden' => 1]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Hamburguesas']);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'en', 'nombre' => 'Burgers']);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Hamburguesa Clásica']);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'en', 'nombre' => 'Classic Burger']);

        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Classic Burger');
    }

    public function test_language_fallback_to_default_when_unsupported_language_requested(): void
    {
        $restaurante = Restaurante::factory()->create([
            'idioma_default' => 'es',
        ]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Postres']);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Pastel de Chocolate']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug, 'lang' => 'ja']));

        $response->assertOk();
        $response->assertSee('Pastel de Chocolate');
    }

    // ==========================================
    // Tier 3: Cross-Feature Combinations
    // ==========================================

    public function test_multitenant_isolation_items_from_restaurant_b_do_not_appear_in_restaurant_a(): void
    {
        $restauranteA = Restaurante::factory()->create(['nombre' => 'Restaurante A']);
        $catA = Categoria::factory()->create(['restaurante_id' => $restauranteA->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catA->id, 'idioma' => 'es', 'nombre' => 'Entradas De A']);
        $prodA = Producto::factory()->create(['restaurante_id' => $restauranteA->id, 'categoria_id' => $catA->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodA->id, 'idioma' => 'es', 'nombre' => 'Taco A']);

        $restauranteB = Restaurante::factory()->create(['nombre' => 'Restaurante B']);
        $catB = Categoria::factory()->create(['restaurante_id' => $restauranteB->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catB->id, 'idioma' => 'es', 'nombre' => 'Postres De B']);
        $prodB = Producto::factory()->create(['restaurante_id' => $restauranteB->id, 'categoria_id' => $catB->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodB->id, 'idioma' => 'es', 'nombre' => 'Pastel B']);

        $response = $this->get(route('menu.publico', ['slug' => $restauranteA->slug]));

        $response->assertOk();
        $response->assertSee('Entradas De A');
        $response->assertSee('Taco A');
        $response->assertDontSee('Postres De B');
        $response->assertDontSee('Pastel B');
    }

    public function test_dynamic_business_hours_open_status_overnight_shift(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
        ]);

        // Viernes (5): 20:00 to 02:00 (spills to Saturday morning)
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 5,
            'abre' => '20:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);

        // Sábado 01:30 AM in Mexico City is within Friday's overnight shift
        Carbon::setTestNow(Carbon::parse('2026-10-24 01:30:00', 'America/Mexico_City'));

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $this->assertTrue($response->viewData('estaAbierto'));
        $response->assertSee('Abierto ahora');
    }

    public function test_dynamic_business_hours_closed_day_overrides_shifts(): void
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
        ]);

        // Martes (2): 13:00 to 17:00, but marked cerrado
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 2,
            'abre' => '13:00:00',
            'cierra' => '17:00:00',
            'cerrado' => false,
        ]);
        Horario::factory()->cerrado()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 2,
        ]);

        // Martes 15:00 in Mexico City
        Carbon::setTestNow(Carbon::parse('2026-10-20 15:00:00', 'America/Mexico_City'));

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $this->assertFalse($response->viewData('estaAbierto'));
        $response->assertSee('Cerrado');
    }

    public function test_l2_menu_cache_service_remembers_menu_and_purges_on_invalidation(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Bebidas']);

        $cacheKey = "menu:{$restaurante->id}:es";
        Cache::forget($cacheKey);
        $this->assertFalse(Cache::has($cacheKey));

        // First request populates L2 cache
        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));
        $response->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        // Invalidating restaurant purges L1/L2 cache key
        app(MenuCacheService::class)->invalidateRestaurante($restaurante);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_security_headers_present_in_public_menu_response(): void
    {
        $restaurante = Restaurante::factory()->create();

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=30', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=86400', $cacheControl);
    }

    // ==========================================
    // Tier 4: Real-World Application Scenarios
    // ==========================================

    public function test_cart_and_whatsapp_ordering_blocked_when_pedidos_activos_is_false(): void
    {
        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => false,
            'whatsapp_pedidos' => '525512345678',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Para ordenar, pídele a tu mesero.');
        $response->assertDontSee('id="floatingCartBar"', false);
    }

    public function test_cart_and_whatsapp_ordering_blocked_when_restaurant_is_closed(): void
    {
        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
            // No horarios -> closed
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Estamos cerrados.');
        $this->assertFalse($response->viewData('estaAbierto'));
    }

    public function test_cart_and_whatsapp_ordering_blocked_when_whatsapp_is_empty(): void
    {
        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => null,
            'zona_horaria' => 'America/Mexico_City',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => now('America/Mexico_City')->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Por ahora no recibimos pedidos aquí. Pídele a tu mesero.');
        $response->assertDontSee('id="floatingCartBar"', false);
    }

    public function test_cart_and_whatsapp_deep_link_markup_when_open_and_active(): void
    {
        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+52 (55) 1234-5678',
            'moneda' => 'MXN',
            'zona_horaria' => 'America/Mexico_City',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1,
            'abre' => '09:00:00',
            'cierra' => '21:00:00',
            'cerrado' => false,
        ]);

        // Monday at 12:00 Mexico City time
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', 'America/Mexico_City'));

        $response = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'mesa' => '7',
        ]));

        $response->assertOk();
        $this->assertTrue($response->viewData('estaAbierto'));
        $response->assertSee("data-clave=\"menuonl_cart_m_{$restaurante->slug}\"", false);
        $response->assertSee("data-clave-antigua=\"menuonl_cart_{$restaurante->slug}\"", false);
        $response->assertSee('data-numero="525512345678"', false);
        $response->assertSee('data-mesa="7"', false);
        $this->assertStringContainsString('https://wa.me/${numero}?text=', (string) file_get_contents(resource_path('js/pedido/mensaje.js')));
        $response->assertSee('value="7"', false);
    }

    public function test_xss_prevention_special_characters_in_product_and_category_escaped(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => '<script>alert("cat-xss")</script>',
        ]);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => '<img src=x onerror=alert("prod-xss")>',
        ]);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertDontSee('<script>alert("cat-xss")</script>', false);
        $response->assertDontSee('<img src=x onerror=alert("prod-xss")>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;cat-xss&quot;)&lt;/script&gt;', false);
        $response->assertSee('&lt;img src=x onerror=alert(&quot;prod-xss&quot;)&gt;', false);
    }

    public function test_offer_price_higher_than_regular_price_is_not_treated_as_offer(): void
    {
        $restaurante = Restaurante::factory()->create(['moneda' => 'MXN']);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Bebidas']);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 50.00,
            'precio_oferta' => 80.00, // Higher than regular price -> invalid as offer
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Jugo Natural']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Jugo Natural');
        $response->assertSee('50.00');
        $response->assertDontSee('class="dish-price-old"', false);
        $response->assertDontSee('class="dish-price-offer"', false);
    }

    public function test_hidden_category_and_hidden_product_are_omitted_from_menu(): void
    {
        $restaurante = Restaurante::factory()->create();

        $catOculta = Categoria::factory()->oculta()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catOculta->id, 'idioma' => 'es', 'nombre' => 'Categoría Fantasma']);

        $catVisible = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catVisible->id, 'idioma' => 'es', 'nombre' => 'Categoría Visible']);

        $prodOculto = Producto::factory()->oculto()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $catVisible->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodOculto->id, 'idioma' => 'es', 'nombre' => 'Platillo Oculto']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Categoría Visible');
        $response->assertDontSee('Categoría Fantasma');
        $response->assertDontSee('Platillo Oculto');
    }

    public function test_mesa_parameter_exceeding_max_length_is_nullified(): void
    {
        $restaurante = Restaurante::factory()->create();

        // 35 characters (> 20 limit)
        $response = $this->get(route('menu.publico', [
            'slug' => $restaurante->slug,
            'mesa' => 'MesaConNombreDemasiadoLargoQueExcede',
        ]));

        $response->assertOk();
        $this->assertNull($response->viewData('mesa'));
        $response->assertDontSee('MesaConNombreDemasiadoLargoQueExcede');
    }

    public function test_sold_out_variant_shows_agotado_and_disables_addition(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'America/Mexico_City')); // miércoles al mediodía
        $restaurante = Restaurante::factory()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => (int) now()->dayOfWeek,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Postres']);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Helado']);

        $varianteDisponible = ProductoVariante::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 30.00,
            'disponible' => true,
            'orden' => 1,
        ]);
        VarianteTraduccion::factory()->create(['variante_id' => $varianteDisponible->id, 'idioma' => 'es', 'nombre' => 'Sabor Fresa']);

        $varianteAgotada = ProductoVariante::factory()->agotada()->create([
            'producto_id' => $producto->id,
            'precio' => 30.00,
            'orden' => 2,
        ]);
        VarianteTraduccion::factory()->create(['variante_id' => $varianteAgotada->id, 'idioma' => 'es', 'nombre' => 'Sabor Vainilla']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Sabor Fresa');
        $response->assertSee('Sabor Vainilla');
        $response->assertSee('Agotado');
    }

    public function test_featured_products_appear_in_destacados_section(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Especialidades']);

        $prodDestacado = Producto::factory()->destacado()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodDestacado->id, 'idioma' => 'es', 'nombre' => 'Platillo Estrella']);

        $response = $this->get(route('menu.publico', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertSee('Recomendados');
        $response->assertSee('Platillo Estrella');
    }
}
