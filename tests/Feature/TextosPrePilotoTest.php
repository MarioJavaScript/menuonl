<?php

namespace Tests\Feature;

use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Models\Horario;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\MarcadoresPendientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Textos de pre-piloto: enlace al aviso de aviso de privacidad en los formularios de reservas (solo si ya está escrito),
 * aviso de producción cuando falta, y la aclaración de que los pedidos son para domicilio o para recoger.
 */
class TextosPrePilotoTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const string FRASE_PEDIDOS = 'Pedidos para domicilio o para recoger.';

    private string $carpetaDeVistas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('backups');
        config(['menuonl.respaldos.disco' => 'backups']);
        $this->withoutVite();

        $this->carpetaDeVistas = sys_get_temp_dir().DIRECTORY_SEPARATOR.'menuonl-privacidad-'.bin2hex(random_bytes(6));
        mkdir($this->carpetaDeVistas, 0777, true);
        file_put_contents($this->carpetaDeVistas.'/pendiente.blade.php', '<p>[PÁGINA POR ESCRIBIR]</p>');
        file_put_contents($this->carpetaDeVistas.'/listo.blade.php', '<p>Texto real del aviso.</p>');
        $this->app['view']->addNamespace('privacidad-prueba', $this->carpetaDeVistas);
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        foreach (glob($this->carpetaDeVistas.'/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        rmdir($this->carpetaDeVistas);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasDelSitio(): array
    {
        return array_combine(
            array_map(fn (string $clave): string => 'plantilla '.strtoupper($clave), range('a', 'h')),
            array_map(fn (string $clave): array => [$clave], range('a', 'h')),
        );
    }

    private function privacidadPendiente(bool $pendiente): void
    {
        $this->mock(MarcadoresPendientes::class, function (MockInterface $mock) use ($pendiente): void {
            $mock->shouldReceive('privacidadPendiente')->andReturn($pendiente);
        });
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function restauranteDelSitio(string $plantilla, array $atributos = []): Restaurante
    {
        $restaurante = Restaurante::factory()->create([
            'sitio_plantilla' => $plantilla,
            'reservas_activas' => true,
            'zona_horaria' => 'America/Mexico_City',
            ...$atributos,
        ]);
        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 3, 'abre' => '07:00:00', 'cierra' => '21:00:00', 'cerrado' => false]);

        return $restaurante;
    }

    public function test_el_aviso_de_privacidad_esta_pendiente_mientras_muestre_un_marcador(): void
    {
        $marcadores = app(MarcadoresPendientes::class);

        $this->assertTrue($marcadores->privacidadPendiente('privacidad-prueba::pendiente'));
        $this->assertFalse($marcadores->privacidadPendiente('privacidad-prueba::listo'));
    }

    #[DataProvider('plantillasDelSitio')]
    public function test_el_formulario_de_reservas_enlaza_el_aviso_de_privacidad_cuando_ya_esta_escrito(string $plantilla): void
    {
        $this->privacidadPendiente(false);
        $restaurante = $this->restauranteDelSitio($plantilla);

        $html = $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();

        $this->assertStringContainsString('id="form-reserva"', $html);
        $this->assertStringContainsString('Consulta el <a href="'.route('privacidad').'">Aviso de privacidad</a>.', $html);
    }

    #[DataProvider('plantillasDelSitio')]
    public function test_sin_aviso_escrito_el_formulario_de_reservas_no_enlaza_nada(string $plantilla): void
    {
        $this->privacidadPendiente(true);
        $restaurante = $this->restauranteDelSitio($plantilla);

        $html = $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();

        $this->assertStringContainsString('id="form-reserva"', $html);
        $this->assertStringNotContainsString(route('privacidad'), $html);
        $this->assertStringNotContainsString('Aviso de privacidad', $html);
    }

    public function test_sin_reservas_activas_no_hay_formulario_ni_enlace(): void
    {
        $this->privacidadPendiente(false);
        $restaurante = $this->restauranteDelSitio('a', ['reservas_activas' => false]);

        $html = $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="form-reserva"', $html);
        $this->assertStringNotContainsString('Aviso de privacidad', $html);
    }

    public function test_verificar_produccion_avisa_si_hay_reservas_activas_y_el_aviso_sigue_pendiente(): void
    {
        $this->privacidadPendiente(true);
        $this->restauranteDelSitio('a');

        Artisan::call('menuonl:verificar-produccion');
        $salida = Artisan::output();

        $this->assertMatchesRegularExpression('/\[AVISO\]\s+Hay restaurantes con reservas activas y el aviso de privacidad/', $salida);
    }

    public function test_verificar_produccion_no_avisa_si_el_aviso_ya_esta_escrito(): void
    {
        $this->privacidadPendiente(false);
        $this->restauranteDelSitio('a');

        Artisan::call('menuonl:verificar-produccion');
        $salida = Artisan::output();

        $this->assertStringNotContainsString('el aviso de privacidad (/privacidad) sigue sin escribirse', $salida);
        $this->assertMatchesRegularExpression('/\[OK\]\s+El aviso de privacidad está escrito/', $salida);
    }

    public function test_verificar_produccion_no_avisa_sin_restaurantes_con_reservas_disponibles(): void
    {
        $this->privacidadPendiente(true);
        $this->restauranteDelSitio('a', ['reservas_activas' => false]);
        $this->restauranteDelSitio('b', ['estado' => 'suspendido']);
        $this->restauranteDelSitio('c', ['plan' => 'solo_menu']);

        Artisan::call('menuonl:verificar-produccion');
        $salida = Artisan::output();

        $this->assertStringNotContainsString('Hay restaurantes con reservas activas', $salida);
        $this->assertStringNotContainsString('El aviso de privacidad está escrito', $salida);
    }

    public function test_verificar_produccion_no_cambia_su_codigo_de_salida_por_este_aviso(): void
    {
        $this->privacidadPendiente(true);
        $this->restauranteDelSitio('a');
        $sinReservas = Artisan::call('menuonl:verificar-produccion');

        $this->privacidadPendiente(false);
        $conAviso = Artisan::call('menuonl:verificar-produccion');

        $this->assertSame($conAviso, $sinReservas);
    }

    public function test_el_menu_publico_aclara_que_los_pedidos_son_para_domicilio_o_recoger(): void
    {
        $restaurante = $this->restauranteDelSitio('a', ['pedidos_activos' => true, 'whatsapp_pedidos' => '5215512345678']);
        $this->travelTo('2026-10-21 18:00:00');

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertOk()
            ->assertSee(self::FRASE_PEDIDOS, false)
            ->assertDontSee('no se atienden en la mesa', false);
    }

    public function test_el_menu_publico_no_dice_nada_de_pedidos_si_estan_apagados(): void
    {
        $restaurante = $this->restauranteDelSitio('a', ['pedidos_activos' => false]);

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertOk()
            ->assertDontSee(self::FRASE_PEDIDOS, false);
    }

    #[DataProvider('plantillasConCajon')]
    public function test_el_cajon_de_las_plantillas_e_a_h_aclara_domicilio_o_recoger(string $plantilla): void
    {
        $restaurante = $this->restauranteDelSitio($plantilla, ['pedidos_activos' => true, 'whatsapp_pedidos' => '5215512345678']);
        $this->travelTo('2026-10-21 18:00:00');

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertOk()
            ->assertSee(self::FRASE_PEDIDOS.' El total es informativo.', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasConCajon(): array
    {
        return ['plantilla E' => ['e'], 'plantilla F' => ['f'], 'plantilla G' => ['g'], 'plantilla H' => ['h']];
    }

    public function test_los_ajustes_del_panel_aclaran_que_los_pedidos_son_para_domicilio_o_recoger(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->assertStringContainsString(self::FRASE_PEDIDOS, __('panel.pedidos.whatsapp.descripcion'));

        $this->get(AjustesDeReservasYPedidos::getUrl())
            ->assertOk()
            ->assertSee(self::FRASE_PEDIDOS, false);
    }
}
