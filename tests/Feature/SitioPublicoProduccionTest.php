<?php

namespace Tests\Feature;

use App\Http\Controllers\MenuPublicoController;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\SitioConfiguracion;
use App\Models\SitioGaleria;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /sitio/{slug} en produccion: mismas protecciones que /m (limitador, cabeceras, Cache-Control, 404),
 * valores por defecto sin configuracion y aislamiento entre restaurantes en una peticion publica sin sesion.
 */
class SitioPublicoProduccionTest extends TestCase
{
    use RefreshDatabase;

    private function restauranteActivo(array $atributos = []): Restaurante
    {
        return Restaurante::factory()->create([
            'estado' => 'activo',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            ...$atributos,
        ]);
    }

    /**
     * Restaurante con configuracion, galeria, contacto, horario y un producto, todos marcados con $marca.
     */
    private function restauranteConContenido(string $marca): Restaurante
    {
        $restaurante = $this->restauranteActivo(['nombre' => "Restaurante {$marca}"]);

        SitioConfiguracion::withoutGlobalScopes()->create([
            'restaurante_id' => $restaurante->id,
            'hero_subtitulo' => "Subtitulo {$marca}",
            'historia_titulo' => "Historia {$marca}",
            'historia_parrafo_1' => "Parrafo {$marca}",
            'estadisticas' => [['etiqueta' => "Dato {$marca}", 'valor' => '10']],
        ]);

        $ruta = "restaurantes/{$restaurante->id}/sitio/foto-{$marca}.webp";
        Storage::disk('public')->put($ruta, 'contenido');
        SitioGaleria::withoutGlobalScopes()->create([
            'restaurante_id' => $restaurante->id,
            'imagen' => $ruta,
            'titulo' => "Foto {$marca}",
            'texto_alternativo' => "Alt {$marca}",
            'orden' => 0,
            'visible' => true,
        ]);

        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'direccion', 'valor' => "Calle {$marca} 1", 'visible' => true]);
        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 2, 'abre' => '13:00:00', 'cierra' => '23:00:00', 'cerrado' => false]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => "Categoria {$marca}"]);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'visible' => true, 'disponible' => true]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => "Plato {$marca}"]);

        return $restaurante;
    }

    private function sitio(string $slug): TestResponse
    {
        // Livewire inyecta sus assets si otro test del mismo proceso renderizo un componente; en una peticion real no ocurre.
        Livewire::flushState();

        return $this->get(route('sitio.prototipo', ['slug' => $slug]));
    }

    private function menu(string $slug): TestResponse
    {
        Livewire::flushState();

        return $this->get(route('menu.publico', ['slug' => $slug]));
    }

    public function test_el_404_de_sitio_es_identico_al_de_menu_para_slug_inexistente_inactivo_y_eliminado(): void
    {
        $suspendido = Restaurante::factory()->create(['estado' => 'suspendido']);
        $eliminado = $this->restauranteActivo();
        $eliminado->delete();

        $slugs = ['no-existe-nunca', $suspendido->slug, $eliminado->slug];
        $cuerpos = [];

        foreach ($slugs as $slug) {
            $sitio = $this->sitio($slug)->assertNotFound();
            $menu = $this->menu($slug)->assertNotFound();

            $this->assertSame($menu->getContent(), $sitio->getContent(), "Cuerpo distinto para {$slug}");
            foreach (['Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options', 'Cache-Control', 'Content-Type'] as $cabecera) {
                $this->assertSame($menu->headers->get($cabecera), $sitio->headers->get($cabecera), "{$cabecera} distinta para {$slug}");
            }

            $cuerpos[] = $sitio->getContent();
        }

        $this->assertCount(1, array_unique($cuerpos), 'Los tres casos de 404 deben ser indistinguibles entre si.');
    }

    public function test_el_404_no_se_cachea_como_el_de_menu(): void
    {
        $this->assertStringNotContainsString('max-age=30', (string) $this->sitio('no-existe')->headers->get('Cache-Control'));
    }

    public function test_el_sitio_envia_las_mismas_cabeceras_de_seguridad_y_cache_que_el_menu(): void
    {
        $restaurante = $this->restauranteActivo();

        $sitio = $this->sitio($restaurante->slug)->assertOk();
        $menu = $this->menu($restaurante->slug)->assertOk();

        foreach (MenuPublicoController::SECURITY_HEADERS as $nombre => $valor) {
            $sitio->assertHeader($nombre, $valor);
        }
        // Symfony reordena las directivas al enviarlas, por eso se revisan por separado y no como cadena exacta.
        foreach (['public', 'max-age=30', 'stale-while-revalidate=86400'] as $directiva) {
            $this->assertStringContainsString($directiva, (string) $sitio->headers->get('Cache-Control'));
        }

        foreach (['Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options', 'Cache-Control'] as $cabecera) {
            $this->assertSame($menu->headers->get($cabecera), $sitio->headers->get($cabecera), "{$cabecera} distinta de /m");
        }
    }

    public function test_el_sitio_usa_el_mismo_limitador_que_el_menu(): void
    {
        $rutas = app('router')->getRoutes();

        $this->assertContains('throttle:menu-publico', $rutas->getByName('sitio.prototipo')->gatherMiddleware());
        $this->assertContains('throttle:menu-publico', $rutas->getByName('menu.publico')->gatherMiddleware());
    }

    public function test_el_limitador_corta_el_sitio_con_429(): void
    {
        RateLimiter::for('menu-publico', fn (): Limit => Limit::perMinute(2)->by('prueba-sitio'));
        $restaurante = $this->restauranteActivo();

        $this->sitio($restaurante->slug)->assertOk();
        $this->sitio($restaurante->slug)->assertOk();
        $this->sitio($restaurante->slug)->assertStatus(429);
        $this->sitio('no-existe')->assertStatus(429);
    }

    public function test_un_restaurante_sin_configuracion_ni_galeria_ni_contenido_se_muestra_con_valores_por_defecto(): void
    {
        $restaurante = $this->restauranteActivo(['nombre' => 'Fonda Vacia', 'whatsapp_pedidos' => null]);
        $this->assertSame(0, SitioConfiguracion::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());
        $this->assertSame(0, SitioGaleria::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count());

        $respuesta = $this->sitio($restaurante->slug)->assertOk();

        $respuesta->assertSee('Fonda Vacia');
        $respuesta->assertSee('id="reservar"', false);
        $respuesta->assertDontSee('id="historia"', false);
        $respuesta->assertDontSee('id="galeria"', false);
        $respuesta->assertDontSee('Créditos de las fotos');
        $respuesta->assertDontSee('data-whatsapp', false);
    }

    public function test_sin_configuracion_las_reservas_quedan_activas_y_con_reservas_desactivadas_se_ocultan(): void
    {
        $restaurante = $this->restauranteActivo();

        $this->sitio($restaurante->slug)->assertOk()->assertSee('Reservar una mesa');

        SitioConfiguracion::factory()->create(['restaurante_id' => $restaurante->id, 'reservas_activas' => false]);

        $this->sitio($restaurante->slug)->assertOk()->assertDontSee('Reservar una mesa');
    }

    public function test_una_configuracion_sin_imagenes_ni_estadisticas_no_da_error(): void
    {
        $restaurante = $this->restauranteActivo();
        SitioConfiguracion::withoutGlobalScopes()->create(['restaurante_id' => $restaurante->id]);

        $this->sitio($restaurante->slug)->assertOk()->assertDontSee('id="historia"', false);
    }

    public function test_cada_sitio_muestra_solo_los_datos_de_su_restaurante(): void
    {
        Storage::fake('public');
        $a = $this->restauranteConContenido('Alfa');
        $b = $this->restauranteConContenido('Beta');

        $paginaA = $this->sitio($a->slug)->assertOk()->getContent();
        $paginaB = $this->sitio($b->slug)->assertOk()->getContent();

        $this->assertStringContainsString('Subtitulo Alfa', $paginaA);
        $this->assertStringContainsString('Foto Alfa', $paginaA);
        $this->assertStringContainsString("restaurantes/{$a->id}/sitio/foto-Alfa.webp", $paginaA);
        $this->assertStringContainsString('Calle Alfa 1', $paginaA);
        $this->assertStringContainsString('Plato Alfa', $paginaA);
        $this->assertStringNotContainsString('Beta', $paginaA);
        $this->assertStringNotContainsString("restaurantes/{$b->id}/", $paginaA);

        $this->assertStringContainsString('Plato Beta', $paginaB);
        $this->assertStringNotContainsString('Alfa', $paginaB);
        $this->assertStringNotContainsString("restaurantes/{$a->id}/", $paginaB);
    }

    public function test_una_peticion_publica_sin_sesion_filtra_por_restaurante_cada_consulta_de_tenant_y_libera_el_scope(): void
    {
        Storage::fake('public');
        $a = $this->restauranteConContenido('Alfa');
        $this->restauranteConContenido('Beta');

        $gramatica = DB::connection()->getQueryGrammar();
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = $consulta->sql;
        });

        $this->sitio($a->slug)->assertOk();

        $columna = $gramatica->wrap('restaurante_id');
        $revisadas = 0;
        foreach (['sitio_configuraciones', 'sitio_galeria', 'contactos', 'horarios', 'categorias', 'productos'] as $tabla) {
            $sobreLaTabla = array_filter($consultas, fn (string $sql): bool => str_contains($sql, 'from '.$gramatica->wrap($tabla)));
            $this->assertNotEmpty($sobreLaTabla, "No se consulto {$tabla}");

            foreach ($sobreLaTabla as $sql) {
                $this->assertStringContainsString($columna, $sql, "Consulta de {$tabla} sin filtro por restaurante: {$sql}");
                $revisadas++;
            }
        }

        $this->assertGreaterThanOrEqual(6, $revisadas);
        $this->assertNull(RestauranteScope::getRestauranteId(), 'El scope del tenant debe liberarse al terminar la peticion.');
    }

    public function test_el_scope_se_libera_tambien_cuando_el_restaurante_no_existe(): void
    {
        $this->sitio('no-existe')->assertNotFound();

        $this->assertNull(RestauranteScope::getRestauranteId());
    }
}
