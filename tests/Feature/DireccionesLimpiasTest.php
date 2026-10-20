<?php

namespace Tests\Feature;

use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Filament\Resources\Restaurantes\Pages\EditRestaurante;
use App\Jobs\PurgarCacheRestauranteJob;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Rules\SlugNoReservado;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Clean public addresses: site at /{slug}, menu at /{slug}/menu, old /s and /m redirecting, reserved slugs.
 */
class DireccionesLimpiasTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    private function restauranteActivo(string $slug = 'casa-lupita'): Restaurante
    {
        return Restaurante::factory()->create(['slug' => $slug, 'nombre' => 'Casa Lupita', 'estado' => 'activo']);
    }

    private function nombreDeRuta(string $uri): ?string
    {
        return app('router')->getRoutes()->match(Request::create($uri))->getName();
    }

    public function test_el_sitio_vive_en_la_raiz_del_slug_y_el_menu_en_slug_menu(): void
    {
        $this->restauranteActivo();

        $this->assertSame('/casa-lupita', route('sitio.publico', ['slug' => 'casa-lupita'], false));
        $this->assertSame('/casa-lupita/menu', route('menu.publico', ['slug' => 'casa-lupita'], false));

        $this->get('/casa-lupita')->assertOk()->assertSee('Casa Lupita')->assertSee('data-plantilla', false);
        $this->get('/casa-lupita/menu')->assertOk()->assertSee('Casa Lupita');
    }

    public function test_el_sitio_enlaza_al_menu_con_la_direccion_nueva(): void
    {
        $this->restauranteActivo()->update(['sitio_plantilla' => 'd']);

        $this->get('/casa-lupita')
            ->assertSee('href="'.route('menu.publico', ['slug' => 'casa-lupita']).'">Menú</a>', false);
    }

    public function test_slug_inexistente_suspendido_o_eliminado_da_el_mismo_404(): void
    {
        $this->restauranteActivo('casa-lupita')->update(['estado' => 'suspendido']);
        $this->restauranteActivo('casa-borrada')->delete();

        foreach (['/%s', '/%s/menu'] as $plantilla) {
            $cuerpos = [];
            foreach (['no-existe', 'casa-lupita', 'casa-borrada'] as $slug) {
                $cuerpos[] = $this->get(sprintf($plantilla, $slug))->assertNotFound()->getContent();
            }
            $this->assertCount(1, array_unique($cuerpos), 'El 404 debe ser idéntico para '.$plantilla);
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function slugsMalFormados(): array
    {
        return [
            'mayúsculas' => ['Casa-Lupita'],
            'punto' => ['casa.lupita'],
            'guion bajo' => ['casa_lupita'],
            'guion al inicio' => ['-casa'],
            'guion al final' => ['casa-'],
            'guiones dobles' => ['casa--lupita'],
            'espacio codificado' => ['casa%20lupita'],
        ];
    }

    #[DataProvider('slugsMalFormados')]
    public function test_un_slug_mal_formado_da_404_en_sitio_y_menu(string $slug): void
    {
        $this->restauranteActivo();

        $this->get('/'.$slug)->assertNotFound();
        $this->get('/'.$slug.'/menu')->assertNotFound();
        $this->get('/m/'.$slug)->assertNotFound();
        $this->get('/s/'.$slug)->assertNotFound();
    }

    public function test_el_slug_no_acepta_segmentos_extra(): void
    {
        $this->restauranteActivo();

        $this->get('/casa-lupita/menu/extra')->assertNotFound();
        $this->get('/casa-lupita/otra')->assertNotFound();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rutasPropiasDeLaAplicacion(): array
    {
        return [
            '/admin' => ['/admin', 'filament.admin.'],
            '/admin/login' => ['/admin/login', 'filament.admin.auth.login'],
            '/panel' => ['/panel', 'panel'],
            '/q/abc123' => ['/q/abc123', 'qr.redirect'],
            '/invitaciones/token' => ['/invitaciones/token-1', 'invitaciones.mostrar'],
            '/api/v1/menu/x' => ['/api/v1/menu/casa-lupita', 'api.v1.menu.show'],
        ];
    }

    #[DataProvider('rutasPropiasDeLaAplicacion')]
    public function test_las_rutas_de_la_aplicacion_no_quedan_tapadas_por_el_slug(string $uri, string $nombreEsperado): void
    {
        $nombre = (string) $this->nombreDeRuta($uri);

        $this->assertNotContains($nombre, ['sitio.publico', 'menu.publico']);
        $this->assertStringStartsWith($nombreEsperado, $nombre);
    }

    public function test_un_slug_reservado_da_404_aunque_exista_un_restaurante_con_ese_slug(): void
    {
        Restaurante::factory()->create(['slug' => 'menu', 'estado' => 'activo']);

        $this->get('/menu')->assertNotFound();
        $this->get('/menu/menu')->assertNotFound();
    }

    public function test_las_rutas_viejas_redirigen_con_301_y_conservan_la_consulta(): void
    {
        $this->restauranteActivo();

        $this->get('/m/casa-lupita?mesa=5&lang=en')
            ->assertStatus(301)
            ->assertRedirect(url('/casa-lupita/menu?mesa=5&lang=en'));

        $this->get('/m/casa-lupita')->assertStatus(301)->assertRedirect(url('/casa-lupita/menu'));
        $this->get('/s/casa-lupita')->assertStatus(301)->assertRedirect(url('/casa-lupita'));
        $this->get('/s/casa-lupita?lang=en')->assertStatus(301)->assertRedirect(url('/casa-lupita?lang=en'));
    }

    public function test_las_rutas_viejas_solo_redirigen_y_no_revelan_si_el_restaurante_existe(): void
    {
        $this->get('/m/no-existe')->assertStatus(301)->assertRedirect(url('/no-existe/menu'));
        $this->get('/no-existe/menu')->assertNotFound();
    }

    public function test_el_qr_lleva_al_menu_nuevo_con_mesa(): void
    {
        $restaurante = $this->restauranteActivo();
        CodigoQr::factory()->deMesa('7')->create(['restaurante_id' => $restaurante->id, 'codigo' => 'lupita07', 'activo' => true]);

        $this->get('/q/lupita07')->assertRedirect(url('/casa-lupita/menu?mesa=7'));
        $this->get('/q/no-existe')->assertNotFound();
    }

    public function test_la_purga_de_cloudflare_cubre_el_menu_y_el_sitio_con_las_direcciones_nuevas(): void
    {
        config([
            'services.cloudflare.zone_id' => 'zona',
            'services.cloudflare.api_token' => 'token',
            'services.cloudflare.domain' => 'ejemplo.test',
        ]);
        Http::fake();

        (new PurgarCacheRestauranteJob(1, 'casa-lupita'))->handle();

        Http::assertSent(function ($peticion): bool {
            return str_contains($peticion->url(), '/zones/zona/purge_cache')
                && $peticion['files'] === ['https://ejemplo.test/casa-lupita/menu', 'https://ejemplo.test/casa-lupita'];
        });
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function slugsReservados(): array
    {
        $casos = [];
        foreach ([...Catalogos::slugsReservados(), 'livewire-b99fc87e'] as $slug) {
            $casos[$slug] = [$slug];
        }

        return $casos;
    }

    #[DataProvider('slugsReservados')]
    public function test_la_regla_rechaza_cada_slug_reservado(string $slug): void
    {
        $validador = Validator::make(['slug' => $slug], ['slug' => [new SlugNoReservado]]);

        $this->assertTrue($validador->fails(), "El slug reservado '{$slug}' debería rechazarse.");
    }

    public function test_la_regla_acepta_slugs_normales_y_responde_en_espanol(): void
    {
        foreach (['casa-lupita', 'la-esquina', 'tacos2', 'a'] as $slug) {
            $this->assertTrue(Validator::make(['slug' => $slug], ['slug' => [new SlugNoReservado]])->passes(), $slug);
        }

        $reservado = Validator::make(['slug' => 'admin'], ['slug' => [new SlugNoReservado]]);
        $this->assertStringContainsString('reservada', $reservado->errors()->first('slug'));

        $this->assertTrue(Validator::make(['slug' => 'Casa Lupita'], ['slug' => [new SlugNoReservado]])->fails());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservadosEnFormularios(): array
    {
        return ['admin' => ['admin'], 'menu' => ['menu'], 'q' => ['q'], 'api' => ['api'], 'livewire' => ['livewire-abc123']];
    }

    #[DataProvider('reservadosEnFormularios')]
    public function test_crear_un_restaurante_con_slug_reservado_se_rechaza(string $slug): void
    {
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $this->crearRestaurante('base'));

        Livewire::test(CreateRestaurante::class)
            ->fillForm([
                'nombre' => 'Nuevo',
                'slug' => $slug,
                'moneda' => 'MXN',
                'idioma_default' => 'es',
                'zona_horaria' => 'America/Mexico_City',
                'estado' => 'activo',
                'email_propietario' => 'dueno@ejemplo.com',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        $this->assertDatabaseMissing('restaurantes', ['slug' => $slug]);
    }

    #[DataProvider('reservadosEnFormularios')]
    public function test_editar_el_slug_de_un_restaurante_a_uno_reservado_se_rechaza(string $slug): void
    {
        $restaurante = $this->crearRestaurante('base');
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $restaurante);

        Livewire::test(EditRestaurante::class, ['record' => $restaurante->getRouteKey()])
            ->fillForm(['slug' => $slug])
            ->call('save')
            ->assertHasFormErrors(['slug']);

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'slug' => 'base']);
    }

    public function test_el_primer_segmento_de_toda_ruta_registrada_es_un_slug_reservado(): void
    {
        $sinReservar = [];

        foreach (app('router')->getRoutes() as $ruta) {
            $primero = explode('/', trim($ruta->uri(), '/'))[0];

            if ($primero === '' || str_starts_with($primero, '{')) {
                continue;
            }

            if (! Catalogos::esSlugReservado($primero)) {
                $sinReservar[$primero] = $ruta->uri();
            }
        }

        $this->assertSame(
            [],
            $sinReservar,
            'Añade estos primeros segmentos a Catalogos::slugsReservados() para que un restaurante no los tape.',
        );
    }

    public function test_el_slug_del_seeder_de_demostracion_no_esta_reservado(): void
    {
        $this->assertFalse(Catalogos::esSlugReservado('la-esquina'));
    }
}
