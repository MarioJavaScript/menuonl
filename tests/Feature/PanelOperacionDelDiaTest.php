<?php

namespace Tests\Feature;

use App\Filament\Pages\Inicio;
use App\Filament\Pages\Reservas;
use App\Filament\Widgets\OperacionDelDiaWidget;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\ReservasDelDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\Concerns\RevisaAtributosWire;
use Tests\TestCase;

/**
 * Operación del día en Inicio (docs/integracion-dashboard.md, sección 20): lo que el HTML de Inicio pinta de verdad, quién
 * lo ve, el cálculo en la zona del restaurante, el sondeo de 60 segundos y las consultas de reservas que hace.
 *
 * El reloj se fija con Carbon::setTestNow. 2031-03-10 06:30 UTC son las 23:30 del día 9 en Hermosillo (UTC-7) y las
 * 00:30 del día 10 en Ciudad de México (UTC-6).
 */
class PanelOperacionDelDiaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase, RevisaAtributosWire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function restauranteDePlan(string $plan, string $slug = 'la-esquina', string $zona = 'America/Mexico_City'): Restaurante
    {
        $restaurante = match ($plan) {
            'sitio_menu' => Restaurante::factory()->sitioMenu()->create(['slug' => $slug, 'zona_horaria' => $zona]),
            'solo_menu' => Restaurante::factory()->soloMenu()->create(['slug' => $slug, 'zona_horaria' => $zona]),
            default => Restaurante::factory()->create(['slug' => $slug, 'zona_horaria' => $zona]),
        };

        return $restaurante;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function reserva(Restaurante $restaurante, array $atributos = []): Reserva
    {
        return Reserva::factory()->create($atributos + [
            'restaurante_id' => $restaurante->id,
            'fecha' => '2026-10-21',
            'hora' => '21:00:00',
            'estado' => 'confirmada',
            'personas' => 2,
            'nombre' => '',
        ]);
    }

    private function entrar(Restaurante $restaurante, string $rol): User
    {
        $usuario = $this->crearUsuario($rol, $restaurante);
        $this->entrarAlPanel($usuario, $restaurante);

        return $usuario;
    }

    // ---------------------------------------------------------------- lo que pinta Inicio

    public function test_inicio_pinta_la_operacion_del_dia_arriba_de_los_accesos_rapidos(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        // Mediodía local (18:00 UTC en Ciudad de México, UTC-6).
        $this->reserva($restaurante, ['nombre' => 'Ana', 'personas' => 4, 'hora' => '21:00:00']);
        $this->reserva($restaurante, ['estado' => 'pendiente', 'personas' => 2, 'hora' => '22:00:00']);
        $this->reserva($restaurante, ['estado' => 'cancelada', 'personas' => 9]);
        $this->reserva($restaurante, ['estado' => 'pendiente', 'fecha' => '2026-10-22']);
        $this->reserva($restaurante, ['estado' => 'pendiente', 'fecha' => '2026-10-20']);

        $this->get(Inicio::getUrl())
            ->assertOk()
            ->assertSeeInOrder(['Hoy en tu restaurante', 'Accesos rápidos'])
            ->assertSee('Tienes 2 reservas para hoy.')
            ->assertSee('Esperas 6 personas.')
            ->assertSee('Hay 2 reservas por confirmar.')
            ->assertSee('Próxima reserva: 21:00, Ana, 4 personas.')
            ->assertSee('Confirmar reservas')
            ->assertSee('href="'.Reservas::getUrl().'"', escape: false)
            ->assertDontSee('Tu día')
            ->assertDontSee('Hoy no hay reservas');
    }

    public function test_la_proxima_reserva_salta_las_que_ya_pasaron_y_las_que_no_esperan(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        Carbon::setTestNow(Carbon::parse('2026-10-22 03:30:00', 'UTC')); // 21:30 del día 21 en Ciudad de México
        $this->reserva($restaurante, ['hora' => '21:00:00', 'nombre' => 'Ya pasó']);
        $this->reserva($restaurante, ['hora' => '21:45:00', 'estado' => 'cancelada', 'nombre' => 'Cancelada']);
        $this->reserva($restaurante, ['hora' => '21:50:00', 'estado' => 'llego', 'nombre' => 'Ya llegó']);
        $this->reserva($restaurante, ['hora' => '22:15:00', 'estado' => 'pendiente', 'nombre' => '', 'personas' => 1]);

        $html = $this->get(Inicio::getUrl())->assertOk()->getContent();

        $this->assertStringContainsString('Próxima reserva: 22:15, 1 persona.', $html);
        $this->assertStringNotContainsString('Ya pasó', $html);
        $this->assertStringNotContainsString('Cancelada', $html);
    }

    public function test_sin_reservas_pero_con_reservas_disponibles_muestra_el_estado_vacio_con_enlace_a_reservas(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $restaurante->update(['reservas_activas' => true]);
        $this->entrar($restaurante, 'propietario');

        $this->get(Inicio::getUrl())
            ->assertOk()
            ->assertSee('Hoy no hay reservas')
            ->assertSee('Cuando alguien reserve desde tu sitio, aparecerá aquí.')
            ->assertSee('class="mb-vacio"', escape: false)
            ->assertSee('href="'.Reservas::getUrl().'"', escape: false)
            ->assertSee('wire:poll.60s.visible', escape: false)
            ->assertDontSee('0 reservas');
    }

    public function test_sin_reservas_y_con_reservas_apagadas_el_bloque_no_se_pinta_ni_se_sondea(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $restaurante->update(['reservas_activas' => false]);
        $this->entrar($restaurante, 'propietario');

        $this->get(Inicio::getUrl())
            ->assertOk()
            ->assertDontSee('Hoy en tu restaurante')
            ->assertDontSee('Hoy no hay reservas')
            ->assertDontSee('wire:poll.60s', escape: false);
    }

    public function test_con_reservas_apagadas_pero_con_reservas_cargadas_a_mano_el_bloque_si_aparece(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $restaurante->update(['reservas_activas' => false]);
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);

        $this->get(Inicio::getUrl())->assertSee('Tienes 1 reserva para hoy.')->assertSee('wire:poll.60s.visible', escape: false);
    }

    // ---------------------------------------------------------------- plan y rol

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function planYRol(): array
    {
        return [
            'propietario completo' => ['completo', 'propietario', true],
            'propietario sitio y menú' => ['sitio_menu', 'propietario', true],
            'superadmin completo' => ['completo', 'superadmin', true],
            'superadmin sitio y menú' => ['sitio_menu', 'superadmin', true],
            'gerente' => ['completo', 'gerente', true],
            'editor' => ['completo', 'editor', true],
            'recepción' => ['completo', 'recepcion', true],
            'propietario solo menú' => ['solo_menu', 'propietario', false],
            'superadmin solo menú' => ['solo_menu', 'superadmin', false],
        ];
    }

    #[DataProvider('planYRol')]
    public function test_solo_lo_ve_quien_puede_abrir_reservas(string $plan, string $rol, bool $loVe): void
    {
        $restaurante = $this->restauranteDePlan($plan);
        $this->entrar($restaurante, $rol);
        $this->reserva($restaurante);

        $respuesta = $this->get(Inicio::getUrl());

        if ($respuesta->getStatusCode() === 200) {
            $loVe ? $respuesta->assertSee('Hoy en tu restaurante') : $respuesta->assertDontSee('Hoy en tu restaurante');
            $this->assertSame($loVe, OperacionDelDiaWidget::canView());
        } else {
            $this->assertFalse($loVe);
            $this->assertFalse(OperacionDelDiaWidget::canView());
        }
    }

    public function test_no_cuenta_reservas_de_otro_restaurante(): void
    {
        $otro = $this->restauranteDePlan('completo', 'otro-local');
        $this->reserva($otro);
        $this->reserva($otro, ['estado' => 'pendiente']);
        $propio = $this->restauranteDePlan('completo', 'la-esquina');
        $this->entrar($propio, 'propietario');

        $dia = app(ReservasDelDia::class)->operacionDelDia($propio);

        $this->assertSame(0, $dia['reservas_hoy']);
        $this->assertSame(0, $dia['por_confirmar']);
        $this->assertNull($dia['proxima']);
    }

    // ---------------------------------------------------------------- zona horaria y medianoche

    /**
     * @return array{0: Restaurante, 1: Restaurante}
     */
    private function hermosilloYMexicoAlLimiteDelDia(): array
    {
        $hermosillo = $this->restauranteDePlan('completo', 'hermosillo', 'America/Hermosillo');
        $mexico = $this->restauranteDePlan('completo', 'mexico', 'America/Mexico_City');

        foreach ([$hermosillo, $mexico] as $restaurante) {
            $this->reserva($restaurante, ['fecha' => '2031-03-09', 'hora' => '23:45:00', 'estado' => 'confirmada']);
            $this->reserva($restaurante, ['fecha' => '2031-03-09', 'hora' => '23:50:00', 'estado' => 'confirmada']);
            $this->reserva($restaurante, ['fecha' => '2031-03-10', 'hora' => '00:15:00', 'estado' => 'pendiente']);
        }

        return [$hermosillo, $mexico];
    }

    public function test_hoy_se_calcula_en_la_zona_del_restaurante_y_no_en_la_del_servidor(): void
    {
        [$hermosillo, $mexico] = $this->hermosilloYMexicoAlLimiteDelDia();
        Carbon::setTestNow(Carbon::parse('2031-03-10 06:30:00', 'UTC'));
        $this->assertSame('UTC', config('app.timezone'));
        $servicio = app(ReservasDelDia::class);

        $dia = $servicio->operacionDelDia($hermosillo);
        $this->assertSame(2, $dia['reservas_hoy'], 'Hermosillo sigue en el día 9: cuentan las dos de las 23:45 y 23:50');
        $this->assertSame(1, $dia['por_confirmar'], 'La pendiente del día 10 cuenta desde hoy');
        $this->assertSame('23:45', $dia['proxima']['hora'], 'Son las 23:30 locales: la próxima es la de las 23:45');

        $dia = $servicio->operacionDelDia($mexico);
        $this->assertSame(1, $dia['reservas_hoy'], 'México ya está en el día 10: solo cuenta la de las 00:15');
        $this->assertSame(1, $dia['por_confirmar']);
        $this->assertNull($dia['proxima'], 'Son las 00:30 locales: la de las 00:15 ya pasó');
    }

    public function test_el_conteo_cambia_al_cruzar_la_medianoche_local(): void
    {
        [$hermosillo] = $this->hermosilloYMexicoAlLimiteDelDia();
        $servicio = app(ReservasDelDia::class);

        Carbon::setTestNow(Carbon::parse('2031-03-09 23:59:00', 'America/Hermosillo'));
        $antes = $servicio->operacionDelDia($hermosillo);
        $this->assertSame(2, $antes['reservas_hoy']);
        $this->assertSame(1, $antes['por_confirmar']);
        $this->assertNull($antes['proxima'], 'A las 23:59 ya no queda una reserva del día 9 por llegar');

        Carbon::setTestNow(Carbon::parse('2031-03-10 00:00:00', 'America/Hermosillo'));
        $despues = $servicio->operacionDelDia($hermosillo);
        $this->assertSame(1, $despues['reservas_hoy']);
        $this->assertSame(1, $despues['por_confirmar']);
        $this->assertSame('00:15', $despues['proxima']['hora']);

        $this->entrar($hermosillo, 'propietario');
        Livewire::test(OperacionDelDiaWidget::class)
            ->assertSee('Tienes 1 reserva para hoy.')
            ->assertSee('Hay 1 reserva por confirmar.')
            ->assertSee('Próxima reserva: 00:15, 2 personas.');
    }

    // ---------------------------------------------------------------- consultas

    public function test_el_widget_hace_como_maximo_dos_consultas_de_reservas(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);
        $this->reserva($restaurante, ['estado' => 'pendiente']);

        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            if (preg_match('/\bfrom ["`]reservas["`]/i', $consulta->sql) === 1) {
                $consultas[] = $consulta->sql;
            }
        });

        Livewire::test(OperacionDelDiaWidget::class)->assertSee('Tienes 2 reservas para hoy.');

        $this->assertCount(2, $consultas, implode("\n", $consultas));
    }

    // ---------------------------------------------------------------- sondeo y sesión caducada

    public function test_el_widget_se_sondea_cada_60_segundos_solo_con_la_pestana_visible(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);

        Livewire::test(OperacionDelDiaWidget::class)
            ->assertSeeHtml('data-sondeo wire:poll.60s.visible');
    }

    public function test_inicio_y_reservas_comparten_el_manejo_de_la_sesion_caducada(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);

        foreach ([Inicio::getUrl(), Reservas::getUrl()] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-sondeo-pagina', $html, $url);
            $this->assertStringContainsString('data-sondeo-aviso', $html, $url);
            $this->assertStringContainsString('window.mbSondeoInterceptor', $html, $url);
            $this->assertStringContainsString('response.status === 419 || response.status === 401', $html, $url);
            $this->assertStringContainsString('pararSondeo()', $html, $url);
            $this->assertStringNotContainsString('data-reservas-', $html, $url);
            $this->assertSame(1, substr_count($html, 'window.mbSondeoInterceptor = true'), $url);
        }
    }

    public function test_el_aviso_de_sesion_de_inicio_lleva_sus_textos_y_el_enlace_para_entrar(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);

        $this->get(Inicio::getUrl())
            ->assertSee('data-texto-red="No pudimos actualizar tu día.', escape: false)
            ->assertSee('data-texto-sesion="Tu sesión expiró.', escape: false)
            ->assertSee('data-sondeo-entrar', escape: false)
            ->assertSee('Entrar de nuevo');
    }

    public function test_el_script_compartido_respeta_la_csp_de_admin_sin_unsafe_inline_en_scripts(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);

        $respuesta = $this->get(Inicio::getUrl())->assertOk();
        $csp = (string) $respuesta->headers->get('Content-Security-Policy');
        preg_match('/script-src[^;]*/', $csp, $directiva);

        $this->assertNotSame('', $csp);
        $this->assertStringContainsString("'nonce-", $directiva[0] ?? '');
        $this->assertStringNotContainsString("'unsafe-inline'", $directiva[0] ?? '');
    }

    public function test_los_atributos_wire_de_la_operacion_del_dia_no_llevan_directivas_ni_json(): void
    {
        $restaurante = $this->restauranteDePlan('completo');
        $this->entrar($restaurante, 'propietario');
        $this->reserva($restaurante);

        $html = $this->get(Inicio::getUrl())->assertOk()->getContent();

        $this->assertNotEmpty($this->valoresDeAtributosDeEventos($html), 'Debe haber al menos el wire:poll del widget.');
        $this->assertAtributosDeEventosLimpios($html);
    }
}
