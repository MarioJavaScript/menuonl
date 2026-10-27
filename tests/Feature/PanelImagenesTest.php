<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\GaleriaImagen;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Services\WebpImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PanelImagenesTest extends TestCase
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

    private function conRol(Restaurante $restaurante, string $rol = 'gerente'): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function url(Restaurante $restaurante, string $ruta): string
    {
        return "/api/v1/panel/{$restaurante->slug}/".$ruta;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function medidas(string $ruta): array
    {
        $info = getimagesizefromstring(Storage::disk('public')->get($ruta));

        return [$info[0], $info[1]];
    }

    public function test_dish_photo_is_cropped_to_4_5_converted_to_webp_with_responsive_copies(): void
    {
        $restaurante = Restaurante::factory()->create();
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id]);

        $r = $this->actingAs($this->conRol($restaurante, 'editor'))
            ->post($this->url($restaurante, "productos/{$producto->id}/imagen"), ['imagen' => UploadedFile::fake()->image('plato.jpg', 2000, 1000)], ['Accept' => 'application/json'])
            ->assertOk();

        $ruta = $producto->refresh()->imagen;
        $this->assertStringStartsWith("restaurantes/{$restaurante->id}/productos/", $ruta);
        $this->assertStringEndsWith('.webp', $ruta);
        [$ancho, $alto] = $this->medidas($ruta);
        $this->assertSame(0.8, round($ancho / $alto, 2));
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($ruta))['mime']);
        $this->assertSame(800, $ancho);
        Storage::disk('public')->assertExists(app(WebpImageService::class)->rutaVariante($ruta, 480));
        $this->assertNotNull($r->json('data.imagen'));
    }

    public function test_hero_is_cropped_to_16_9_and_old_files_are_deleted_on_replacement(): void
    {
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante);
        $servicio = app(WebpImageService::class);

        $this->actingAs($gerente)->post($this->url($restaurante, 'ajustes/hero'), ['imagen' => UploadedFile::fake()->image('a.png', 2400, 1600)], ['Accept' => 'application/json'])->assertOk();
        $primera = $restaurante->refresh()->portada;
        [$ancho, $alto] = $this->medidas($primera);
        $this->assertSame(1.78, round($ancho / $alto, 2));
        $this->assertSame(1600, $ancho);

        $this->actingAs($gerente)->post($this->url($restaurante, 'ajustes/hero'), ['imagen' => UploadedFile::fake()->image('b.jpg', 1800, 900)], ['Accept' => 'application/json'])->assertOk();

        $this->assertNotSame($primera, $restaurante->refresh()->portada);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertMissing($servicio->rutaVariante($primera, 480));

        $this->actingAs($gerente)->deleteJson($this->url($restaurante, 'ajustes/hero'))->assertOk();
        $this->assertNull($restaurante->refresh()->portada);
    }

    public function test_small_images_still_work_with_a_single_copy(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->actingAs($this->conRol($restaurante))
            ->post($this->url($restaurante, 'ajustes/galeria'), ['imagen' => UploadedFile::fake()->image('chica.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertCreated();

        $ruta = GaleriaImagen::withoutGlobalScopes()->first()->imagen;
        [$ancho, $alto] = $this->medidas($ruta);
        $this->assertSame([240, 300], [$ancho, $alto]);
        $this->assertNull(app(WebpImageService::class)->srcset($ruta));
    }

    public function test_gallery_upload_limit_and_delete_remove_files(): void
    {
        config(['menuonl.imagenes.max_galeria' => 2]);
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante);
        $subir = fn () => $this->actingAs($gerente)->post($this->url($restaurante, 'ajustes/galeria'), ['imagen' => UploadedFile::fake()->image('g.jpg', 1000, 1000)], ['Accept' => 'application/json']);

        $id = $subir()->assertCreated()->json('data.id');
        $subir()->assertCreated();
        $subir()->assertUnprocessable();

        $ruta = GaleriaImagen::withoutGlobalScopes()->find($id)->imagen;
        $this->actingAs($gerente)->deleteJson($this->url($restaurante, "ajustes/galeria/{$id}"))->assertNoContent();
        $this->assertDatabaseMissing('galeria_imagenes', ['id' => $id]);
        Storage::disk('public')->assertMissing($ruta);
    }

    public function test_settings_expose_hero_and_gallery(): void
    {
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante);
        $this->actingAs($gerente)->post($this->url($restaurante, 'ajustes/galeria'), ['imagen' => UploadedFile::fake()->image('g.jpg', 1000, 1000)], ['Accept' => 'application/json']);

        $this->actingAs($gerente)->getJson($this->url($restaurante, 'ajustes'))
            ->assertJsonCount(1, 'data.galeria')->assertJsonPath('data.hero', null)->assertJsonPath('data.galeria_max', 12);
    }

    public function test_rejects_svg_text_oversized_and_oversized_dimensions(): void
    {
        $restaurante = Restaurante::factory()->create();
        $gerente = $this->conRol($restaurante);
        $url = $this->url($restaurante, 'ajustes/hero');
        $enviar = fn (UploadedFile $f) => $this->actingAs($gerente)->post($url, ['imagen' => $f], ['Accept' => 'application/json']);

        $enviar(UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))->assertUnprocessable();
        $enviar(UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1; ?>'))->assertUnprocessable();
        $enviar(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))->assertUnprocessable();
        $enviar(UploadedFile::fake()->image('enorme.jpg', 5000, 100))->assertUnprocessable();

        config(['menuonl.imagenes.max_bytes' => 1024]);
        $enviar(UploadedFile::fake()->image('pesada.jpg', 1500, 1500)->size(4000))->assertUnprocessable();

        $this->actingAs($gerente)->postJson($url, [])->assertJsonValidationErrors('imagen');
        $this->assertNull($restaurante->refresh()->portada);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_roles_without_the_module_cannot_upload(): void
    {
        $restaurante = Restaurante::factory()->create();
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id]);
        $foto = ['imagen' => UploadedFile::fake()->image('a.jpg', 800, 800)];

        foreach (['editor', 'recepcion'] as $rol) {
            $this->actingAs($this->conRol($restaurante, $rol))->post($this->url($restaurante, 'ajustes/hero'), $foto, ['Accept' => 'application/json'])->assertForbidden();
            $this->actingAs($this->conRol($restaurante, $rol))->post($this->url($restaurante, 'ajustes/galeria'), $foto, ['Accept' => 'application/json'])->assertForbidden();
        }

        foreach (['recepcion'] as $rol) {
            $this->actingAs($this->conRol($restaurante, $rol))->post($this->url($restaurante, "productos/{$producto->id}/imagen"), $foto, ['Accept' => 'application/json'])->assertForbidden();
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_cannot_attach_photos_to_other_restaurants_records(): void
    {
        $propio = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();
        $productoAjeno = Producto::factory()->create(['restaurante_id' => $ajeno->id]);
        $fotoAjena = GaleriaImagen::factory()->create(['restaurante_id' => $ajeno->id]);
        $gerente = $this->conRol($propio);

        $this->actingAs($gerente)->post($this->url($propio, "productos/{$productoAjeno->id}/imagen"), ['imagen' => UploadedFile::fake()->image('a.jpg', 800, 800)], ['Accept' => 'application/json'])->assertNotFound();
        $this->actingAs($gerente)->deleteJson($this->url($propio, "ajustes/galeria/{$fotoAjena->id}"))->assertNotFound();
        $this->actingAs($gerente)->post($this->url($ajeno, 'ajustes/hero'), ['imagen' => UploadedFile::fake()->image('a.jpg', 800, 800)], ['Accept' => 'application/json'])->assertNotFound();

        $this->assertNull($productoAjeno->refresh()->imagen);
        $this->assertDatabaseHas('galeria_imagenes', ['id' => $fotoAjena->id]);
    }

    public function test_menu_listing_includes_the_photo_url_or_null(): void
    {
        $restaurante = Restaurante::factory()->create();
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        $conFoto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        $gerente = $this->conRol($restaurante);
        $this->actingAs($gerente)->post($this->url($restaurante, "productos/{$conFoto->id}/imagen"), ['imagen' => UploadedFile::fake()->image('a.jpg', 1000, 1250)], ['Accept' => 'application/json'])->assertOk();

        $imagenes = collect($this->actingAs($gerente)->getJson($this->url($restaurante, 'menu'))->json('data.0.productos'))->pluck('imagen');

        $this->assertCount(1, $imagenes->filter());
        $this->assertCount(1, $imagenes->reject());
    }
}
