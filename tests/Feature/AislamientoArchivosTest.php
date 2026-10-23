<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Filament\Resources\Productos\Pages\EditProducto;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Services\WebpImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Image files are isolated per restaurant: stored only as WebP under restaurantes/{id}/, never
 * assigned from, read from or deleted from another restaurant's directory, even with a tampered Livewire payload.
 */
class AislamientoArchivosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $a;

    private Restaurante $b;

    private string $rutaDeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        Storage::fake('public');
        $this->a = $this->crearRestaurante('resto-a');
        $this->b = $this->crearRestaurante('resto-b');

        $this->rutaDeB = "restaurantes/{$this->b->id}/productos/secreta.webp";
        Storage::disk('public')->put($this->rutaDeB, 'contenido-de-b');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesDePanel(): array
    {
        return ['propietario' => ['propietario'], 'editor' => ['editor']];
    }

    public function test_guarda_solo_el_webp_en_el_directorio_del_restaurante_y_nunca_el_original(): void
    {
        $ruta = app(WebpImageService::class)->storeForRestaurante(
            UploadedFile::fake()->image('Mi Foto Original.png', 200, 100),
            $this->a->id,
            'productos',
        );

        $this->assertStringStartsWith("restaurantes/{$this->a->id}/productos/", $ruta);
        $this->assertStringEndsWith('.webp', $ruta);
        Storage::disk('public')->assertExists($ruta);

        $archivos = Storage::disk('public')->allFiles("restaurantes/{$this->a->id}");
        $this->assertSame([$ruta], $archivos);
        $this->assertSame([], Storage::disk('public')->allFiles('livewire-tmp'));
        $this->assertSame('image/webp', Storage::disk('public')->mimeType($ruta));
    }

    public function test_carpeta_fuera_de_la_lista_blanca_se_rechaza(): void
    {
        $this->expectException(ValidationException::class);

        app(WebpImageService::class)->storeForRestaurante(UploadedFile::fake()->image('a.png'), $this->a->id, '../b/productos');
    }

    public function test_rechaza_svg_aunque_se_llame_png_y_valida_el_mime_real(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $texto = UploadedFile::fake()->createWithContent('foto.jpg', 'esto no es una imagen');

        foreach ([$svg, $texto] as $archivo) {
            try {
                app(WebpImageService::class)->storeForRestaurante($archivo, $this->a->id, 'logos');
                $this->fail('Debió rechazar el archivo.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame([], Storage::disk('public')->allFiles("restaurantes/{$this->a->id}"));
    }

    public function test_el_tope_en_bytes_se_aplica_antes_de_decodificar_la_imagen(): void
    {
        config(['menuonl.imagenes.max_bytes' => 500]);

        $grande = UploadedFile::fake()->createWithContent('grande.png', str_repeat('A', 5000));

        try {
            app(WebpImageService::class)->storeForRestaurante($grande, $this->a->id, 'portadas');
            $this->fail('Debió rechazar por peso.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('demasiado grande', $e->errors()['imagen'][0]);
        }

        $this->assertSame([], Storage::disk('public')->allFiles("restaurantes/{$this->a->id}"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rutasQueEscapanDelRestaurante(): array
    {
        return [
            'otro restaurante' => ['restaurantes/2/productos/x.webp'],
            'prefijo parecido' => ['restaurantes/11/productos/x.webp'],
            'traversal' => ['restaurantes/1/../2/productos/x.webp'],
            'traversal al final' => ['restaurantes/1/productos/..'],
            'absoluta' => ['/restaurantes/1/productos/x.webp'],
            'backslash' => ['restaurantes\\1\\productos\\x.webp'],
            'solo el directorio' => ['restaurantes/1/'],
            'segmento vacio' => ['restaurantes/1//x.webp'],
            'byte nulo' => ["restaurantes/1/productos/x.webp\0.png"],
            'fuera de restaurantes' => ['private/secreto.webp'],
            'vacia' => [''],
        ];
    }

    #[DataProvider('rutasQueEscapanDelRestaurante')]
    public function test_la_ruta_debe_estar_dentro_de_restaurantes_id(string $ruta): void
    {
        $servicio = app(WebpImageService::class);

        $this->assertFalse($servicio->rutaPerteneceAlRestaurante($ruta, 1));
        $this->assertFalse($servicio->deleteFromRestaurante($ruta, 1));
    }

    public function test_acepta_una_ruta_propia_valida(): void
    {
        $this->assertTrue(app(WebpImageService::class)->rutaPerteneceAlRestaurante('restaurantes/1/productos/abc.webp', 1));
    }

    public function test_borrar_desde_otro_restaurante_no_toca_el_archivo(): void
    {
        $this->assertFalse(app(WebpImageService::class)->deleteFromRestaurante($this->rutaDeB, $this->a->id));

        Storage::disk('public')->assertExists($this->rutaDeB);
    }

    public function test_el_modelo_rechaza_asignar_una_imagen_de_otro_restaurante(): void
    {
        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id]);

        try {
            $producto->update(['imagen' => $this->rutaDeB]);
            $this->fail('Debió rechazar la ruta ajena.');
        } catch (ValidationException) {
            $this->assertNull($producto->fresh()->imagen);
        }

        try {
            $this->a->update(['logo' => $this->rutaDeB]);
            $this->fail('Debió rechazar la ruta ajena.');
        } catch (ValidationException) {
            $this->assertNull($this->a->fresh()->logo);
        }
    }

    public function test_reemplazar_una_imagen_borra_la_anterior_solo_si_es_propia(): void
    {
        $propia = "restaurantes/{$this->a->id}/productos/vieja.webp";
        $nueva = "restaurantes/{$this->a->id}/productos/nueva.webp";
        Storage::disk('public')->put($propia, 'vieja');

        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id, 'imagen' => $propia]);
        $producto->update(['imagen' => $nueva]);

        Storage::disk('public')->assertMissing($propia);
    }

    public function test_reemplazar_una_imagen_heredada_ajena_no_borra_el_archivo_ajeno(): void
    {
        // Legacy/corrupt row that points into another restaurant's directory (inserted without the model)
        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id]);
        Producto::withoutGlobalScopes()->whereKey($producto->id)->toBase()->update(['imagen' => $this->rutaDeB]);

        $producto = Producto::withoutGlobalScopes()->find($producto->id);
        $producto->update(['imagen' => "restaurantes/{$this->a->id}/productos/nueva.webp"]);

        Storage::disk('public')->assertExists($this->rutaDeB);
    }

    #[DataProvider('rolesDePanel')]
    public function test_livewire_no_asigna_la_imagen_de_otro_restaurante_a_un_producto(string $rol): void
    {
        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id]);
        $this->entrarAlPanel($this->crearUsuario($rol, $this->a), $this->a);

        Livewire::test(EditProducto::class, ['record' => $producto->getRouteKey()])
            ->set('data.imagen', ['uuid-1' => $this->rutaDeB])
            ->call('save')
            ->assertHasErrors();

        $this->assertNull($producto->fresh()->imagen);
        Storage::disk('public')->assertExists($this->rutaDeB);
    }

    #[DataProvider('rolesDePanel')]
    public function test_livewire_no_asigna_la_imagen_de_otro_restaurante_al_crear_un_producto(string $rol): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario($rol, $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->set('data.categoria_id', $categoria->id)
            ->set('data.precio', 50)
            ->set('data.orden', 0)
            ->set('data.imagen', ['uuid-1' => $this->rutaDeB])
            ->call('create')
            ->assertHasErrors();

        $this->assertSame(0, Producto::withoutGlobalScopes()->count());
    }

    public function test_livewire_no_asigna_logo_ni_portada_ajenos_en_la_configuracion(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(Apariencia::class)
            ->set('data.logo', ['uuid-1' => $this->rutaDeB])
            ->set('data.portada', ['uuid-2' => $this->rutaDeB])
            ->call('save')
            ->assertHasErrors();

        $this->assertNull($this->a->fresh()->logo);
        $this->assertNull($this->a->fresh()->portada);
        Storage::disk('public')->assertExists($this->rutaDeB);
    }

    public function test_livewire_no_borra_la_imagen_ajena_al_quitarla_del_formulario(): void
    {
        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(EditProducto::class, ['record' => $producto->getRouteKey()])
            ->set('data.imagen', ['uuid-1' => $this->rutaDeB])
            ->call('callSchemaComponentMethod', 'form.imagen', 'deleteUploadedFile', ['uuid-1']);

        Storage::disk('public')->assertExists($this->rutaDeB);
    }

    public function test_subir_una_imagen_en_el_panel_guarda_solo_el_webp_en_el_directorio_del_restaurante_activo(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(Apariencia::class)
            ->set('data.logo', UploadedFile::fake()->image('logo-original.jpg', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $logo = $this->a->fresh()->logo;

        $this->assertStringStartsWith("restaurantes/{$this->a->id}/logos/", $logo);
        $this->assertStringEndsWith('.webp', $logo);
        $this->assertSame([$logo], Storage::disk('public')->allFiles("restaurantes/{$this->a->id}"));
        $this->assertNull($this->b->fresh()->logo);
    }

    public function test_subir_un_svg_en_el_panel_se_rechaza_y_no_guarda_nada(): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(Apariencia::class)
            ->set('data.logo', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'))
            ->call('save')
            ->assertHasErrors();

        $this->assertNull($this->a->fresh()->logo);
        $this->assertSame([$this->rutaDeB], Storage::disk('public')->allFiles('restaurantes'));
    }
}
