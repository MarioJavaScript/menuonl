<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Páginas públicas sin estado (docs/despliegue.md, H1): ni cookies, ni sesión, ni token CSRF, para que Cloudflare pueda
 * guardarlas en caché y los bots no llenen la tabla sessions. El panel conserva su sesión normal.
 */
class PaginasPublicasSinSesionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RestauranteScope::setRestauranteId(null);
        Cache::flush();
        RateLimiter::clear('menu-publico:127.0.0.1');

        // Con el driver de base de datos cualquier sesión iniciada dejaría una fila en sessions.
        config(['session.driver' => 'database']);
        $this->app['session']->setDefaultDriver('database');
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasPublicas(): array
    {
        return [
            'sitio' => ['sitio'],
            'menu' => ['menu'],
            'qr general' => ['qr'],
            'ruta anterior del sitio' => ['anterior-sitio'],
            'ruta anterior del menu' => ['anterior-menu'],
            'no disponible' => ['inexistente'],
        ];
    }

    private function visitar(string $ruta): TestResponse
    {
        $restaurante = Restaurante::factory()->create();
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id, 'codigo' => 'pub'.fake()->unique()->numerify('#####')]);

        $url = match ($ruta) {
            'sitio' => route('sitio.publico', ['slug' => $restaurante->slug]),
            'menu' => route('menu.publico', ['slug' => $restaurante->slug]),
            'qr' => route('qr.redirect', ['codigo' => $qr->codigo]),
            'anterior-sitio' => '/s/'.$restaurante->slug,
            'anterior-menu' => '/m/'.$restaurante->slug,
            'inexistente' => '/'.$restaurante->slug.'-no-existe',
        };

        return $this->get($url);
    }

    #[DataProvider('rutasPublicas')]
    public function test_las_rutas_publicas_no_envian_ninguna_cookie(string $ruta): void
    {
        $respuesta = $this->visitar($ruta);

        $this->assertFalse($respuesta->headers->has('Set-Cookie'));
        $this->assertSame([], $respuesta->headers->getCookies());
        $this->assertNotContains('laravel-session', array_map(fn ($c) => $c->getName(), $respuesta->headers->getCookies()));
    }

    #[DataProvider('rutasPublicas')]
    public function test_visitar_las_rutas_publicas_no_crea_filas_en_sessions(string $ruta): void
    {
        $antes = DB::table('sessions')->count();

        $this->visitar($ruta);
        $this->visitar($ruta);

        $this->assertSame($antes, DB::table('sessions')->count());
    }

    public function test_el_menu_y_el_sitio_conservan_su_cache_control_publico(): void
    {
        $restaurante = Restaurante::factory()->create();

        foreach (['menu.publico', 'sitio.publico'] as $nombre) {
            $cabecera = (string) $this->get(route($nombre, ['slug' => $restaurante->slug]))->assertOk()->headers->get('Cache-Control');

            $this->assertStringContainsString('public', $cabecera);
            $this->assertStringContainsString('max-age=30', $cabecera);
            $this->assertStringNotContainsString('private', $cabecera);
        }
    }

    public function test_las_paginas_no_traen_token_csrf_ni_formularios_que_lo_necesiten(): void
    {
        $restaurante = Restaurante::factory()->create();

        foreach ([route('menu.publico', ['slug' => $restaurante->slug]), route('sitio.publico', ['slug' => $restaurante->slug])] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('name="_token"', false)
                ->assertDontSee('csrf-token', false);
        }
    }

    public function test_el_limite_de_intentos_sigue_protegiendo_las_rutas_publicas(): void
    {
        $restaurante = Restaurante::factory()->create();
        $url = route('menu.publico', ['slug' => $restaurante->slug]);

        $limite = null;

        for ($i = 0; $i < 400; $i++) {
            $respuesta = $this->get($url);

            if ($respuesta->getStatusCode() === 429) {
                $limite = $i;
                break;
            }
        }

        $this->assertNotNull($limite, 'El rate limit menu-publico dejó de aplicarse.');
        $this->assertFalse($respuesta->headers->has('Set-Cookie'));
    }

    private function restauranteBilingue(): Restaurante
    {
        $restaurante = Restaurante::factory()->create(['idioma_default' => 'es']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'en', 'orden' => 1]);
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Hamburguesas']);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'en', 'nombre' => 'Burgers']);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Hamburguesa Clásica']);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'en', 'nombre' => 'Classic Burger']);

        return $restaurante;
    }

    public function test_el_idioma_se_toma_del_parametro_lang_sin_sesion(): void
    {
        $restaurante = $this->restauranteBilingue();

        $this->withHeader('Accept-Language', 'es-ES,es;q=0.9')
            ->get(route('menu.publico', ['slug' => $restaurante->slug, 'lang' => 'en']))
            ->assertSee('Classic Burger')
            ->assertDontSee('Hamburguesa Clásica');
    }

    public function test_el_idioma_se_toma_de_accept_language_sin_sesion(): void
    {
        $restaurante = $this->restauranteBilingue();

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertSee('Classic Burger');
    }

    public function test_cada_visita_decide_su_idioma_sin_recordar_la_anterior(): void
    {
        $restaurante = $this->restauranteBilingue();

        $this->get(route('menu.publico', ['slug' => $restaurante->slug, 'lang' => 'en']))->assertSee('Classic Burger');
        $this->get(route('menu.publico', ['slug' => $restaurante->slug, 'lang' => 'es']))
            ->assertSee('Hamburguesa Clásica')
            ->assertDontSee('Classic Burger');
    }

    public function test_la_mesa_viaja_por_la_url_y_el_qr_de_mesa_la_conserva(): void
    {
        $restaurante = Restaurante::factory()->create();
        $qr = CodigoQr::factory()->deMesa('7')->create(['restaurante_id' => $restaurante->id, 'codigo' => 'mesa'.fake()->unique()->numerify('####')]);

        $this->get(route('qr.redirect', ['codigo' => $qr->codigo]))
            ->assertRedirect(route('menu.publico', ['slug' => $restaurante->slug, 'mesa' => '7']));

        $this->get(route('menu.publico', ['slug' => $restaurante->slug, 'mesa' => '7']))
            ->assertOk()
            ->assertViewHas('mesa', '7');

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertViewHas('mesa', null);
    }

    public function test_el_menu_cerrado_se_sigue_mostrando_como_cerrado(): void
    {
        $restaurante = Restaurante::factory()->create(['pedidos_activos' => true, 'whatsapp_pedidos' => '525512345678']);

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertOk()
            ->assertSee('Estamos cerrados.');
    }

    public function test_los_planes_siguen_igual_y_solo_menu_redirige_sin_cookies(): void
    {
        $completo = Restaurante::factory()->create();
        $sitioMenu = Restaurante::factory()->sitioMenu()->create();
        $soloMenu = Restaurante::factory()->soloMenu()->create();

        $this->get('/'.$completo->slug)->assertOk()->assertSee('id="form-reserva"', false);
        $this->get('/'.$sitioMenu->slug)->assertOk()->assertSee('id="form-reserva"', false);

        $redireccion = $this->get('/'.$soloMenu->slug.'?lang=en&mesa=5')
            ->assertStatus(302)
            ->assertRedirect(url('/'.$soloMenu->slug.'/menu?lang=en&mesa=5'));

        $this->assertFalse($redireccion->headers->has('Set-Cookie'));
        $this->get('/'.$soloMenu->slug.'/menu')->assertOk();
    }

    public function test_el_panel_de_login_sigue_usando_sesion_y_cookies(): void
    {
        $antes = DB::table('sessions')->count();

        $respuesta = $this->get('/admin/login');

        $respuesta->assertOk();
        $nombres = array_map(fn ($cookie) => $cookie->getName(), $respuesta->headers->getCookies());

        $this->assertContains(config('session.cookie'), $nombres);
        $this->assertContains('XSRF-TOKEN', $nombres);
        $this->assertGreaterThan($antes, DB::table('sessions')->count());
    }

    public function test_el_panel_de_servicio_sigue_pidiendo_sesion(): void
    {
        $this->get('/panel')->assertRedirect('/admin/login');
    }
}
