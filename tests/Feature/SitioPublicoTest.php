<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\GaleriaImagen;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function restaurante(array $atributos = []): Restaurante
    {
        return Restaurante::factory()->create(['nombre' => 'Mesa Larga', 'slug' => 'mesa-larga', ...$atributos]);
    }

    private function platillo(Restaurante $restaurante, string $nombre, array $atributos = [], ?Categoria $categoria = null): Producto
    {
        $categoria ??= Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, ...$atributos]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre, 'descripcion' => 'Descripción de '.$nombre]);

        return $producto;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function plantillas(): array
    {
        return ['A minimal' => ['a', 'sitio-a'], 'B oscuro' => ['b', 'sitio-b'], 'C cálido' => ['c', 'sitio-c']];
    }

    #[DataProvider('plantillas')]
    public function test_each_template_renders_real_data_and_its_own_markup(string $clave, string $clase): void
    {
        $r = $this->restaurante(['sitio_plantilla' => $clave, 'eslogan' => 'Cocina de fuego', 'descripcion' => 'Una casa pequeña con brasas.', 'whatsapp_pedidos' => '+52 55 1234 5678']);
        $this->platillo($r, 'Risotto de hongos', ['precio' => 280, 'destacado' => true]);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => 'Calle Real 45']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 2, 'abre' => '13:00:00', 'cierra' => '23:00:00', 'cerrado' => false]);

        $respuesta = $this->get('/mesa-larga')->assertOk();

        $respuesta->assertSee($clase, false)->assertSee('data-plantilla="'.$clave.'"', false)
            ->assertSee('Mesa Larga')->assertSee('Cocina de fuego')->assertSee('Una casa pequeña con brasas.')
            ->assertSee('Risotto de hongos')->assertSee('$280.00')->assertSee('Calle Real 45')
            ->assertSee('tel:5511112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('13:00 – 23:00')->assertSee('Martes')->assertSee('Cerrado')
            ->assertSee('/fonts/sitio/inter-latin-variable.woff2', false);
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_it_never_shows_sample_data(): void
    {
        $this->restaurante(['whatsapp_pedidos' => null]);

        $html = $this->get('/mesa-larga')->assertOk()->getContent();

        $this->assertStringNotContainsString('Casa Alba', $html);
        $this->assertStringNotContainsString('525500000000', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_the_restaurants_chosen_template_is_the_one_shown_and_a_change_applies(): void
    {
        $r = $this->restaurante(['sitio_plantilla' => 'a']);
        $this->get('/mesa-larga')->assertSee('data-plantilla="a"', false);

        $r->update(['sitio_plantilla' => 'c']);
        $this->get('/mesa-larga')->assertSee('data-plantilla="c"', false)->assertDontSee('data-plantilla="a"', false);

        $r->forceFill(['sitio_plantilla' => 'z'])->saveQuietly();
        $this->get('/mesa-larga')->assertOk()->assertSee('data-plantilla="a"', false);
    }

    public function test_hidden_dishes_never_appear_and_sold_out_ones_are_flagged_or_removed(): void
    {
        $r = $this->restaurante(['mostrar_agotados' => true]);
        $categoria = Categoria::factory()->create(['restaurante_id' => $r->id]);
        $this->platillo($r, 'Plato visible', [], $categoria);
        $this->platillo($r, 'Plato oculto', ['visible' => false], $categoria);
        $this->platillo($r, 'Plato agotado', ['disponible' => false], $categoria);

        $this->get('/mesa-larga')->assertSee('Plato visible')->assertDontSee('Plato oculto')->assertSee('Plato agotado')->assertSee('Agotado');

        $r->update(['mostrar_agotados' => false]);
        $this->get('/mesa-larga')->assertSee('Plato visible')->assertDontSee('Plato oculto')->assertDontSee('Plato agotado');
    }

    public function test_featured_dishes_are_preferred_and_the_list_is_capped(): void
    {
        $r = $this->restaurante();
        $categoria = Categoria::factory()->create(['restaurante_id' => $r->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Fuertes']);
        $this->platillo($r, 'Estrella', ['destacado' => true], $categoria);
        $this->platillo($r, 'Comun', [], $categoria);

        $this->get('/mesa-larga')->assertSee('Estrella')->assertDontSee('Comun');

        Producto::withoutGlobalScopes()->where('restaurante_id', $r->id)->update(['destacado' => false]);
        foreach (range(1, 9) as $n) {
            $this->platillo($r, "Extra {$n}", ['orden' => $n], $categoria);
        }
        $html = $this->get('/mesa-larga')->getContent();
        $this->assertSame(6, substr_count($html, '<article'));
        $this->assertStringNotContainsString('Extra 9', $html);
    }

    public function test_photos_use_responsive_sources_and_missing_ones_show_a_neutral_placeholder(): void
    {
        $r = $this->restaurante();
        $this->platillo($r, 'Sin foto');
        Storage::disk('public')->put("restaurantes/{$r->id}/portadas/h.webp", 'x');
        Storage::disk('public')->put("restaurantes/{$r->id}/portadas/h-w480.webp", 'x');
        Storage::disk('public')->put("restaurantes/{$r->id}/portadas/h-w960.webp", 'x');
        $r->update(['portada' => "restaurantes/{$r->id}/portadas/h.webp"]);

        $html = $this->get('/mesa-larga')->assertOk()->getContent();

        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('-w480.webp 480w', $html);
        $this->assertStringContainsString('foto-vacia', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function test_gallery_section_appears_only_with_photos(): void
    {
        $r = $this->restaurante();
        $this->get('/mesa-larga')->assertDontSee('id="galeria"', false);

        GaleriaImagen::factory()->create(['restaurante_id' => $r->id]);
        $this->get('/mesa-larga')->assertSee('id="galeria"', false);
    }

    public function test_social_links_are_safe(): void
    {
        $r = $this->restaurante();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'instagram', 'valor' => '@mesa.larga']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'facebook', 'valor' => 'javascript:alert(1)']);

        $this->get('/mesa-larga')->assertSee('https://instagram.com/mesa.larga', false)->assertDontSee('javascript:', false);
    }

    public function test_user_content_is_escaped(): void
    {
        $r = $this->restaurante(['nombre' => 'Mesa <script>alert(1)</script>', 'descripcion' => '<img src=x onerror=alert(2)>']);
        $this->platillo($r, '<b>negrita</b>');

        $html = $this->get('/mesa-larga')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<b>negrita</b>', $html);
    }

    public function test_never_mixes_data_between_restaurants(): void
    {
        $a = $this->restaurante();
        $b = Restaurante::factory()->create(['nombre' => 'Otro Lugar', 'slug' => 'otro-lugar']);
        $this->platillo($a, 'Plato de A');
        $this->platillo($b, 'Plato de B');
        Contacto::factory()->create(['restaurante_id' => $b->id, 'tipo' => 'direccion', 'valor' => 'Calle de B 99']);
        Horario::factory()->create(['restaurante_id' => $b->id, 'dia_semana' => 1, 'abre' => '09:00:00', 'cierra' => '10:00:00', 'cerrado' => false]);

        $this->get('/mesa-larga')->assertSee('Plato de A')->assertDontSee('Plato de B')->assertDontSee('Calle de B 99')->assertDontSee('09:00 – 10:00');
        $this->assertNull(RestauranteScope::getRestauranteId());
    }

    public function test_unknown_suspended_and_deleted_restaurants_answer_404(): void
    {
        $this->get('/no-existe')->assertNotFound();
        $this->restaurante(['estado' => 'suspendido']);
        $this->get('/mesa-larga')->assertNotFound();

        Restaurante::where('slug', 'mesa-larga')->update(['estado' => 'activo']);
        Restaurante::where('slug', 'mesa-larga')->first()->delete();
        $this->get('/mesa-larga')->assertNotFound();
    }
}
