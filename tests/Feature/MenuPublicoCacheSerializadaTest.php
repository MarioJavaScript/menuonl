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
use App\Models\User;
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

    /**
     * Test that unwhitelisted classes (e.g. App\Models\User or arbitrary objects)
     * deserialize strictly as __PHP_Incomplete_Class when cached.
     */
    public function test_deserializacion_de_clases_no_autorizadas_produce_php_incomplete_class_y_no_instancia_el_objeto(): void
    {
        // 1. Modelo User (no incluido en config('cache.serializable_classes'))
        $usuario = new User(['name' => 'Usuario No Autorizado', 'email' => 'hacker@evil.test']);
        Cache::store('file')->put('adversarial_user', $usuario, 60);

        $recuperadoUsuario = Cache::store('file')->get('adversarial_user');
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $recuperadoUsuario);
        $this->assertNotInstanceOf(User::class, $recuperadoUsuario);

        // 2. Objeto arbitrario de clase no autorizada (no anónima)
        $objetoArbitrario = new ObjetoNoAutorizadoPrueba;
        Cache::store('file')->put('adversarial_anon', $objetoArbitrario, 60);

        $recuperadoAnon = Cache::store('file')->get('adversarial_anon');
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $recuperadoAnon);
        $this->assertNotInstanceOf(ObjetoNoAutorizadoPrueba::class, $recuperadoAnon);
    }

    /**
     * Test that corrupted serialized cache payload recovers gracefully without HTTP 500.
     */
    public function test_recuperacion_transparente_y_sin_error_500_ante_payload_de_cache_corrupto(): void
    {
        $restaurante = $this->crearMenuCompleto();
        $url = route('menu.publico', ['slug' => $restaurante->slug]);

        // 1. Petición inicial para poblar la caché
        $this->get($url)->assertOk()->assertSee('Arrachera cacheada');
        $this->assertTrue(Cache::store('file')->has("menu:{$restaurante->id}:es"));

        // 2. Corromper los archivos de caché generados en el directorio de prueba
        $archivos = File::allFiles($this->directorioCache);
        $this->assertNotEmpty($archivos, 'Se esperaba encontrar al menos un archivo de caché generado.');

        foreach ($archivos as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.lock')) {
                file_put_contents($archivo->getRealPath(), (time() + 3600).'O:11:"Restaurante":999:{corrupt_payload_broken_bytes!!!');
            }
        }

        // 3. La siguiente petición debe recuperarse de forma transparente y servir el menú sin HTTP 500
        $respuesta = $this->get($url);
        $respuesta->assertOk();
        $respuesta->assertSee('Arrachera cacheada');
        $respuesta->assertSee('Platos fuertes');
    }
}

class ObjetoNoAutorizadoPrueba
{
    public string $payload = 'exploit_call';
}
