<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Services\EnlaceGoogleMaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Datos del restaurante vistos por el propietario: textos del sitio, ubicación por enlace de Google Maps y moneda.
 */
class PanelDatosYMapaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const ENLACE_LARGO = 'https://www.google.com/maps/place/Taqueria+Lupe/@19.432608,-99.133209,17z/data=!3m1!4b1';

    protected function setUp(): void
    {
        parent::setUp();

        EnlaceGoogleMaps::usarResolutor(fn (string $host): array => ['142.250.80.46']);
    }

    protected function tearDown(): void
    {
        EnlaceGoogleMaps::usarResolutor(null);

        parent::tearDown();
    }

    public function test_el_propietario_edita_nombre_frase_descripcion_y_moneda(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->assertSee('Datos del restaurante')
            ->assertSee('Ver mi sitio')
            ->assertSee('Ver mi menú')
            ->fillForm([
                'nombre' => 'Taquería Lupe',
                'eslogan' => 'Tacos de la casa',
                'descripcion' => 'Cocina de barrio.',
                'moneda' => 'USD',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertSame('Taquería Lupe', $restaurante->nombre);
        $this->assertSame('Tacos de la casa', $restaurante->eslogan);
        $this->assertSame('USD', $restaurante->moneda);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['nombre' => ''])
            ->call('save')
            ->assertHasErrors(['data.nombre' => 'required']);
    }

    public function test_el_dueno_no_puede_cambiar_la_direccion_corta_ni_el_estado(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->assertDontSee('slug')
            ->assertDontSee('Dirección corta')
            ->set('data.slug', 'otro-nombre')
            ->set('data.estado', 'suspendido')
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertSame('la-esquina', $restaurante->slug);
        $this->assertSame('activo', $restaurante->estado);
    }

    public function test_el_dueno_no_ve_jerga_ni_la_palabra_carta(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        preg_match('#<main.*?</main>#s', $this->get(EditRestauranteProfile::getUrl())->getContent(), $principal);
        $texto = strip_tags($principal[0] ?? '');
        $this->assertNotSame('', $texto);

        foreach (['slug', 'plantilla_sitio', 'carta', 'Carta'] as $palabra) {
            $this->assertStringNotContainsString($palabra, $texto);
        }
    }

    public function test_un_enlace_largo_de_google_maps_guarda_latitud_y_longitud(): void
    {
        Http::fake();
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => self::ENLACE_LARGO])
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertEqualsWithDelta(19.432608, (float) $restaurante->latitud, 0.000001);
        $this->assertEqualsWithDelta(-99.133209, (float) $restaurante->longitud, 0.000001);
        Http::assertNothingSent();
    }

    public function test_un_enlace_corto_de_compartir_se_sigue_y_se_guarda_la_ubicacion(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => self::ENLACE_LARGO])]);
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => 'https://maps.app.goo.gl/AbCdEf123'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(19.432608, (float) $restaurante->fresh()->latitud, 0.000001);
        Http::assertSentCount(1);
    }

    public function test_un_enlace_corto_que_lleva_a_otro_dominio_se_rechaza_sin_visitarlo(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://evil.example/maps/@1.5,2.5'])]);
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => 'https://maps.app.goo.gl/AbCdEf123'])
            ->call('save')
            ->assertHasErrors(['data.enlace_mapa']);

        $this->assertNull($restaurante->fresh()->latitud);
        Http::assertSentCount(1);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function enlacesNoPermitidos(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'http sin cifrar' => ['http://www.google.com/maps/@19.4,-99.1,17z'],
            'otro dominio' => ['https://evil.example/maps/@19.4,-99.1,17z'],
            'dominio que solo empieza como google' => ['https://google.com.evil.example/maps/@19.4,-99.1,17z'],
            'google fuera de mapas' => ['https://www.google.com/search?q=19.4,-99.1'],
            'con usuario' => ['https://usuario@www.google.com/maps/@19.4,-99.1,17z'],
            'con puerto' => ['https://www.google.com:8443/maps/@19.4,-99.1,17z'],
            'data uri' => ['data:text/html,<script>alert(1)</script>'],
            'ftp' => ['ftp://www.google.com/maps/@19.4,-99.1,17z'],
            'texto cualquiera' => ['mi restaurante'],
        ];
    }

    #[DataProvider('enlacesNoPermitidos')]
    public function test_enlaces_que_no_son_de_google_maps_se_rechazan(string $enlace): void
    {
        Http::fake();
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => $enlace])
            ->call('save')
            ->assertHasErrors(['data.enlace_mapa']);

        $this->assertNull($restaurante->fresh()->latitud);
        $this->assertNull($restaurante->fresh()->longitud);
        Http::assertNothingSent();
        $this->assertFalse(EnlaceGoogleMaps::esValido($enlace));
    }

    public function test_un_enlace_de_google_maps_sin_ubicacion_explica_que_hacer(): void
    {
        Http::fake();
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['enlace_mapa' => 'https://www.google.com/maps/place/Taqueria+Lupe'])
            ->call('save')
            ->assertHasErrors(['data.enlace_mapa'])
            ->assertSee('toca Compartir');
    }

    public function test_dejar_el_enlace_vacio_conserva_la_ubicacion_guardada_y_se_puede_quitar(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $restaurante->update(['latitud' => 19.432608, 'longitud' => -99.133209]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(EditRestauranteProfile::class)
            ->assertSee('Ya tienes una ubicación guardada')
            ->fillForm(['nombre' => 'Otro nombre'])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNotNull($restaurante->fresh()->latitud);

        Livewire::test(EditRestauranteProfile::class)
            ->fillForm(['quitar_mapa' => true])
            ->call('save')
            ->assertHasNoErrors();

        $restaurante->refresh();
        $this->assertNull($restaurante->latitud);
        $this->assertNull($restaurante->longitud);
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_los_demas_roles_no_ven_datos_del_restaurante(string $rol): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        $this->assertFalse(EditRestauranteProfile::canView($restaurante));
        Livewire::test(EditRestauranteProfile::class)->assertNotFound();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinAcceso(): array
    {
        return ['editor' => ['editor'], 'gerente' => ['gerente'], 'recepcion' => ['recepcion'], 'cocina' => ['cocina']];
    }

    /**
     * @return array<string, array{0: string, 1: ?array{latitud: float, longitud: float}}>
     */
    public static function enlacesConCoordenadas(): array
    {
        return [
            'arroba' => ['https://www.google.com/maps/place/X/@19.432608,-99.133209,17z', ['latitud' => 19.432608, 'longitud' => -99.133209]],
            'pin del lugar' => ['https://www.google.com/maps/place/X/@19.5,-99.2,17z/data=!3d19.432608!4d-99.133209', ['latitud' => 19.432608, 'longitud' => -99.133209]],
            'consulta' => ['https://www.google.com/maps?q=19.4326,-99.1332', ['latitud' => 19.4326, 'longitud' => -99.1332]],
            'hemisferio sur' => ['https://www.google.com/maps/@-34.603722,-58.381592,15z', ['latitud' => -34.603722, 'longitud' => -58.381592]],
            'dominio con pais' => ['https://www.google.com.mx/maps/@19.4326,-99.1332,15z', ['latitud' => 19.4326, 'longitud' => -99.1332]],
            'maps.google.com' => ['https://maps.google.com/?ll=19.4326,-99.1332', ['latitud' => 19.4326, 'longitud' => -99.1332]],
            'fuera de rango' => ['https://www.google.com/maps/@95.0,-99.1332,15z', null],
            'sin coordenadas' => ['https://www.google.com/maps/place/Lupe', null],
        ];
    }

    /**
     * @param  ?array{latitud: float, longitud: float}  $esperado
     */
    #[DataProvider('enlacesConCoordenadas')]
    public function test_extrae_las_coordenadas_del_enlace(string $enlace, ?array $esperado): void
    {
        Http::fake();

        $this->assertSame($esperado, EnlaceGoogleMaps::coordenadas($enlace));
    }
}
