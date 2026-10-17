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
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use App\Services\MenuCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The rest of the suite uses the array cache store, which never serializes. These tests use a store that does
 * (as database, file or redis do in production) so cached Eloquent objects go through serialize/unserialize.
 */
class MenuPublicoCacheSerializadaTest extends TestCase
{
    use RefreshDatabase;

    private string $directorioCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorioCache = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-cache-'.uniqid();
        File::ensureDirectoryExists($this->directorioCache);

        config([
            'cache.default' => 'file',
            'cache.stores.file.path' => $this->directorioCache,
            'cache.stores.file.lock_path' => $this->directorioCache,
        ]);
        Cache::purge('file');
        Cache::store('file')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();
        Cache::purge('file');
        File::deleteDirectory($this->directorioCache);
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    private function crearMenuCompleto(): Restaurante
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'cache-serializada']);

        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 1]);
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Platos fuertes']);

        $producto = Producto::factory()->destacado()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'visible' => true,
            'disponible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Arrachera cacheada']);
        $producto->etiquetas()->attach(Etiqueta::factory()->create()->id);

        $variante = ProductoVariante::factory()->create(['producto_id' => $producto->id]);
        VarianteTraduccion::factory()->create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Porción doble']);

        return $restaurante;
    }

    public function test_la_segunda_peticion_lee_el_menu_serializado_de_la_cache_sin_error(): void
    {
        $restaurante = $this->crearMenuCompleto();
        $url = route('menu.publico', ['slug' => $restaurante->slug]);

        $this->get($url)->assertOk()->assertSee('Arrachera cacheada');
        $this->assertTrue(Cache::has("menu:{$restaurante->id}:es"));

        $this->get($url)
            ->assertOk()
            ->assertSee('Arrachera cacheada')
            ->assertSee('Porción doble')
            ->assertSee('Platos fuertes');
    }

    public function test_invalidar_el_restaurante_purga_la_cache_serializada(): void
    {
        $restaurante = $this->crearMenuCompleto();
        $url = route('menu.publico', ['slug' => $restaurante->slug]);

        $this->get($url)->assertOk();
        $this->assertTrue(Cache::has("menu:{$restaurante->id}:es"));

        app(MenuCacheService::class)->invalidateRestaurante($restaurante);

        $this->assertFalse(Cache::has("menu:{$restaurante->id}:es"));
        $this->get($url)->assertOk()->assertSee('Arrachera cacheada');
    }

    public function test_cache_clear_antes_y_despues_no_rompe_el_menu(): void
    {
        $restaurante = $this->crearMenuCompleto();
        $url = route('menu.publico', ['slug' => $restaurante->slug]);

        $this->artisan('cache:clear')->assertSuccessful();
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->artisan('cache:clear')->assertSuccessful();
        $this->assertFalse(Cache::has("menu:{$restaurante->id}:es"));
        $this->get($url)->assertOk()->assertSee('Arrachera cacheada');
    }
}
