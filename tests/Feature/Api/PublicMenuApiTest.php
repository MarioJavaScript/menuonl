<?php

namespace Tests\Feature\Api;

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMenuApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    public function test_public_menu_api_returns_200_with_complete_json_structure(): void
    {
        $now = Carbon::parse('2026-10-19 12:00:00', 'America/Mexico_City'); // Monday noon
        Carbon::setTestNow($now);

        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Taquería La Auténtica',
            'slug' => 'taqueria-la-autentica',
            'eslogan' => 'Los mejores tacos al pastor',
            'descripcion' => 'Tradición familiar desde 1985',
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+525512345678',
            'mostrar_agotados' => true,
            'estado' => 'activo',
            'tema' => 'calido',
            'color_acento' => '#a8541c',
        ]);

        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1, // Monday
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ]);

        Contacto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'tipo' => 'whatsapp',
            'etiqueta' => 'WhatsApp',
            'valor' => '+525512345678',
            'orden' => 1,
            'visible' => true,
        ]);

        $categoria = Categoria::factory()->create([
            'restaurante_id' => $restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);
        CategoriaTraduccion::factory()->create([
            'categoria_id' => $categoria->id,
            'idioma' => 'es',
            'nombre' => 'Tacos',
            'descripcion' => 'Tacos preparados al pastor',
        ]);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 45.00,
            'precio_oferta' => 38.00,
            'disponible' => true,
            'visible' => true,
            'destacado' => true,
            'orden' => 1,
        ]);
        ProductoTraduccion::factory()->create([
            'producto_id' => $producto->id,
            'idioma' => 'es',
            'nombre' => 'Taco al Pastor',
            'descripcion' => 'Con piña, cilantro y cebolla',
        ]);

        $tag = Etiqueta::firstOrCreate(['codigo' => 'recomendado', 'icono' => 'star']);
        $producto->etiquetas()->attach($tag->id);

        $variante = ProductoVariante::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 55.00,
            'disponible' => true,
            'orden' => 1,
        ]);
        VarianteTraduccion::factory()->create([
            'variante_id' => $variante->id,
            'idioma' => 'es',
            'nombre' => 'Con queso',
        ]);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'nombre',
                'slug',
                'eslogan',
                'descripcion',
                'logo_url',
                'portada_url',
                'monograma',
                'tema',
                'color_acento',
                'color_acento_texto',
                'moneda',
                'idioma',
                'idioma_default',
                'idiomas_disponibles',
                'zona_horaria',
                'abierto',
                'pedidos_activos',
                'puede_pedir',
                'whatsapp_pedidos',
                'whatsapp_url',
                'mostrar_agotados',
                'mesa',
                'horarios' => [
                    '*' => [
                        'id',
                        'dia_semana',
                        'dia_nombre',
                        'abre',
                        'cierra',
                        'abre_formateado',
                        'cierra_formateado',
                        'rango_formateado',
                        'cerrado',
                    ],
                ],
                'contactos' => [
                    '*' => [
                        'id',
                        'tipo',
                        'tipo_nombre',
                        'etiqueta',
                        'valor',
                        'orden',
                        'url',
                    ],
                ],
                'categorias' => [
                    '*' => [
                        'id',
                        'orden',
                        'nombre',
                        'descripcion',
                        'platillos' => [
                            '*' => [
                                'id',
                                'categoria_id',
                                'nombre',
                                'descripcion',
                                'precio',
                                'precio_formateado',
                                'precio_oferta',
                                'precio_oferta_formateado',
                                'precio_vigente',
                                'precio_vigente_formateado',
                                'tiene_oferta',
                                'tiene_variantes',
                                'imagen_url',
                                'disponible',
                                'destacado',
                                'orden',
                                'etiquetas' => [
                                    '*' => [
                                        'id',
                                        'codigo',
                                        'nombre',
                                        'icono',
                                    ],
                                ],
                                'variantes' => [
                                    '*' => [
                                        'id',
                                        'producto_id',
                                        'nombre',
                                        'precio',
                                        'precio_formateado',
                                        'disponible',
                                        'orden',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'destacados' => [
                    '*' => [
                        'id',
                        'categoria_id',
                        'nombre',
                        'precio',
                        'precio_formateado',
                    ],
                ],
            ],
        ]);

        $response->assertJsonPath('data.nombre', 'Taquería La Auténtica');
        $response->assertJsonPath('data.abierto', true);
        $response->assertJsonPath('data.puede_pedir', true);
        $response->assertJsonPath('data.whatsapp_url', 'https://wa.me/525512345678');
        $response->assertJsonPath('data.categorias.0.platillos.0.tiene_oferta', true);
        $response->assertJsonPath('data.categorias.0.platillos.0.precio_vigente', 38);
    }

    public function test_public_menu_api_formats_prices_and_offer_prices_correctly(): void
    {
        $restaurante = Restaurante::factory()->create([
            'moneda' => 'MXN',
            'idioma_default' => 'es',
        ]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Carnes']);

        // Dish 1: Valid offer
        $dishOffer = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 100.00,
            'precio_oferta' => 80.00,
            'orden' => 1,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $dishOffer->id, 'idioma' => 'es', 'nombre' => 'Corte con Descuento']);

        // Dish 2: False offer (higher than regular price)
        $dishFalseOffer = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 100.00,
            'precio_oferta' => 120.00,
            'orden' => 2,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $dishFalseOffer->id, 'idioma' => 'es', 'nombre' => 'Corte Oferta Invalida']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $dishes = $response->json('data.categorias.0.platillos');

        // Check Dish 1
        $this->assertEquals(100.0, $dishes[0]['precio']);
        $this->assertEquals(80.0, $dishes[0]['precio_oferta']);
        $this->assertEquals(80.0, $dishes[0]['precio_vigente']);
        $this->assertTrue($dishes[0]['tiene_oferta']);
        $this->assertStringContainsString('80.00', $dishes[0]['precio_vigente_formateado']);

        // Check Dish 2 (False offer rejected)
        $this->assertEquals(100.0, $dishes[1]['precio']);
        $this->assertNull($dishes[1]['precio_oferta']);
        $this->assertEquals(100.0, $dishes[1]['precio_vigente']);
        $this->assertFalse($dishes[1]['tiene_oferta']);
        $this->assertStringContainsString('100.00', $dishes[1]['precio_vigente_formateado']);
    }

    public function test_public_menu_api_formats_foreign_currencies_with_code(): void
    {
        $restaurante = Restaurante::factory()->create([
            'moneda' => 'USD',
            'idioma_default' => 'es',
        ]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'American']);

        $producto = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'precio' => 25.00,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Burger']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $precioFormateado = $response->json('data.categorias.0.platillos.0.precio_formateado');
        $this->assertStringContainsString('USD', $precioFormateado);
    }

    public function test_public_menu_api_generates_monogram_when_logo_is_null(): void
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Ñandú Bistro',
            'logo' => null,
        ]);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertJsonPath('data.logo_url', null);
        $response->assertJsonPath('data.monograma', 'Ñ');
    }

    public function test_public_menu_api_supports_multilingual_via_lang_query_param(): void
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en']);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Hamburguesas', 'descripcion' => 'Ricas']);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'en', 'nombre' => 'Burgers', 'descripcion' => 'Delicious']);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Hamburguesa Clásica']);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'en', 'nombre' => 'Classic Burger']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'lang' => 'en']));

        $response->assertOk();
        $response->assertJsonPath('data.idioma', 'en');
        $response->assertJsonPath('data.categorias.0.nombre', 'Burgers');
        $response->assertJsonPath('data.categorias.0.descripcion', 'Delicious');
        $response->assertJsonPath('data.categorias.0.platillos.0.nombre', 'Classic Burger');
    }

    public function test_public_menu_api_ignores_accept_language_and_uses_the_default_language(): void
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en']);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Pizzas']);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'en', 'nombre' => 'Pizzas EN']);

        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertJsonPath('data.idioma', 'es');
        $response->assertJsonPath('data.categorias.0.nombre', 'Pizzas');

        $this->withHeader('Accept-Language', 'es')
            ->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'lang' => 'en']))
            ->assertJsonPath('data.idioma', 'en')
            ->assertJsonPath('data.categorias.0.nombre', 'Pizzas EN');
    }

    public function test_public_menu_api_falls_back_to_default_language_for_unsupported_language(): void
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Bebidas']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'lang' => 'ja']));

        $response->assertOk();
        $response->assertJsonPath('data.idioma', 'es');
        $response->assertJsonPath('data.categorias.0.nombre', 'Bebidas');
    }

    public function test_public_menu_api_falls_back_to_default_translation_when_item_translation_missing(): void
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en']);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Postres']);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'en', 'nombre' => 'Desserts']);

        // Dish only has Spanish translation
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Flan Casero']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'lang' => 'en']));

        $response->assertOk();
        $response->assertJsonPath('data.categorias.0.nombre', 'Desserts');
        $response->assertJsonPath('data.categorias.0.platillos.0.nombre', 'Flan Casero');
    }

    public function test_public_menu_api_sanitizes_mesa_query_parameter(): void
    {
        $restaurante = Restaurante::factory()->create();

        // Valid mesa
        $responseValid = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => 'Mesa-12']));
        $responseValid->assertOk();
        $responseValid->assertJsonPath('data.mesa', 'Mesa-12');

        // Invalid mesa (malicious characters)
        $responseInvalid = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug, 'mesa' => '<script>alert(1)</script>']));
        $responseInvalid->assertOk();
        $responseInvalid->assertJsonPath('data.mesa', null);
    }

    public function test_public_menu_api_returns_404_for_nonexistent_restaurant(): void
    {
        $response = $this->getJson(route('api.v1.menu.show', ['slug' => 'slug-que-no-existe-xyz']));

        $response->assertNotFound();
        $response->assertJson(['message' => 'Restaurante no encontrado o no disponible.']);
    }

    public function test_public_menu_api_returns_404_for_suspended_restaurant(): void
    {
        $restaurante = Restaurante::factory()->create(['estado' => 'suspendido']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertNotFound();
        $response->assertJson(['message' => 'Restaurante no encontrado o no disponible.']);
    }

    public function test_public_menu_api_returns_404_for_soft_deleted_restaurant(): void
    {
        $restaurante = Restaurante::factory()->create();
        $slug = $restaurante->slug;
        $restaurante->delete();

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $slug]));

        $response->assertNotFound();
        $response->assertJson(['message' => 'Restaurante no encontrado o no disponible.']);
    }

    public function test_public_menu_api_returns_200_with_abierto_false_when_outside_hours(): void
    {
        // Monday 03:00 AM in Mexico City
        Carbon::setTestNow(Carbon::parse('2026-10-19 03:00:00', 'America/Mexico_City'));

        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
        ]);

        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 1, // Monday
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ]);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertJsonPath('data.abierto', false);
        $response->assertJsonPath('data.puede_pedir', false);
    }

    public function test_public_menu_api_evaluates_overnight_shifts_correctly(): void
    {
        // Shift is Friday (5) from 20:00 to 02:00 (Saturday morning rollover)
        $restaurante = Restaurante::factory()->create(['zona_horaria' => 'America/Mexico_City']);

        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 5, // Friday
            'abre' => '20:00:00',
            'cierra' => '02:00:00',
            'cerrado' => false,
        ]);

        // Test at Saturday (6) 01:30 AM
        Carbon::setTestNow(Carbon::parse('2026-10-24 01:30:00', 'America/Mexico_City'));

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertJsonPath('data.abierto', true);
    }

    public function test_public_menu_api_strictly_enforces_multitenant_isolation(): void
    {
        $restauranteA = Restaurante::factory()->create(['nombre' => 'Restaurante Alfa']);
        $catA = Categoria::factory()->create(['restaurante_id' => $restauranteA->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catA->id, 'idioma' => 'es', 'nombre' => 'Cat Alfa']);
        $prodA = Producto::factory()->create(['restaurante_id' => $restauranteA->id, 'categoria_id' => $catA->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodA->id, 'idioma' => 'es', 'nombre' => 'Plato Alfa']);

        $restauranteB = Restaurante::factory()->create(['nombre' => 'Restaurante Beta']);
        $catB = Categoria::factory()->create(['restaurante_id' => $restauranteB->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catB->id, 'idioma' => 'es', 'nombre' => 'Cat Beta']);
        $prodB = Producto::factory()->create(['restaurante_id' => $restauranteB->id, 'categoria_id' => $catB->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodB->id, 'idioma' => 'es', 'nombre' => 'Plato Beta']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restauranteA->slug]));

        $response->assertOk();
        $response->assertJsonPath('data.nombre', 'Restaurante Alfa');
        $response->assertJsonPath('data.categorias.0.nombre', 'Cat Alfa');
        $response->assertJsonPath('data.categorias.0.platillos.0.nombre', 'Plato Alfa');

        // Assert zero leakage of Restaurant B
        $response->assertDontSee('Cat Beta');
        $response->assertDontSee('Plato Beta');

        // Assert scope is clean afterwards
        $this->assertNull(RestauranteScope::getRestauranteId());
    }

    public function test_public_menu_api_filters_sold_out_items_when_mostrar_agotados_is_false(): void
    {
        $restaurante = Restaurante::factory()->create(['mostrar_agotados' => false]);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'General']);

        $prodDisponible = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'orden' => 1,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodDisponible->id, 'idioma' => 'es', 'nombre' => 'Platillo Disponible']);

        $prodAgotado = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => false,
            'orden' => 2,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodAgotado->id, 'idioma' => 'es', 'nombre' => 'Platillo Agotado']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $platillos = $response->json('data.categorias.0.platillos');
        $this->assertCount(1, $platillos);
        $this->assertEquals('Platillo Disponible', $platillos[0]['nombre']);
    }

    public function test_public_menu_api_includes_sold_out_items_when_mostrar_agotados_is_true(): void
    {
        $restaurante = Restaurante::factory()->create(['mostrar_agotados' => true]);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'General']);

        $prodAgotado = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => false,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodAgotado->id, 'idioma' => 'es', 'nombre' => 'Platillo Agotado']);

        $response = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $platillos = $response->json('data.categorias.0.platillos');
        $this->assertCount(1, $platillos);
        $this->assertEquals('Platillo Agotado', $platillos[0]['nombre']);
        $this->assertFalse($platillos[0]['disponible']);
    }

    public function test_public_menu_api_respects_order_eligibility_conditions(): void
    {
        $now = Carbon::parse('2026-10-19 12:00:00', 'America/Mexico_City');
        Carbon::setTestNow($now);

        // Case 1: WhatsApp orders disabled in settings
        $restaurante1 = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => false,
            'whatsapp_pedidos' => '+525512345678',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante1->id,
            'dia_semana' => 1,
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ]);

        $response1 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante1->slug]));
        $response1->assertOk();
        $response1->assertJsonPath('data.puede_pedir', false);

        // Case 2: Open and active, but no WhatsApp configured
        $restaurante2 = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => null,
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante2->id,
            'dia_semana' => 1,
            'abre' => '10:00:00',
            'cierra' => '22:00:00',
            'cerrado' => false,
        ]);

        $response2 = $this->getJson(route('api.v1.menu.show', ['slug' => $restaurante2->slug]));
        $response2->assertOk();
        $response2->assertJsonPath('data.puede_pedir', false);
    }

    public function test_public_menu_api_responds_with_cors_headers_for_localhost_3000(): void
    {
        $restaurante = Restaurante::factory()->create();

        $response = $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('api.v1.menu.show', ['slug' => $restaurante->slug]));

        $response->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_public_menu_api_responds_to_options_preflight(): void
    {
        $restaurante = Restaurante::factory()->create();

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type, Accept',
        ])->options('/api/v1/menu/'.$restaurante->slug);

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
    }
}
