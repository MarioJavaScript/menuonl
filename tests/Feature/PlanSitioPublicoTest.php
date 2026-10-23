<?php

namespace Tests\Feature;

use App\Models\CodigoQr;
use App\Models\Horario;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sitio público por plan (docs/planes.md, paso 2): Solo menú no tiene página de sitio ni reservas en línea.
 */
class PlanSitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    /** Miércoles 2026-10-21, 10:00 en Ciudad de México. */
    private const AHORA = '2026-10-21 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::AHORA, 'America/Mexico_City'));
        Cache::flush();
        RateLimiter::clear('reserva:min:127.0.0.1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function conHorarioDelMiercoles(Restaurante $restaurante): void
    {
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 3,
            'abre' => '13:00:00',
            'cierra' => '23:00:00',
            'cerrado' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeReserva(): array
    {
        return [
            'nombre' => 'Lucía Márquez',
            'telefono' => '+52 55 9999 0000',
            'personas' => 4,
            'fecha' => '2026-10-21',
            'hora' => '20:00',
            'nota' => null,
            'ts' => now()->timestamp - 10,
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function plantillasDelSitio(): array
    {
        return ['a' => ['a'], 'b' => ['b'], 'c' => ['c'], 'd' => ['d'], 'e' => ['e'], 'f' => ['f'], 'g' => ['g'], 'h' => ['h']];
    }

    #[DataProvider('plantillasDelSitio')]
    public function test_completo_y_sitio_y_menu_muestran_el_sitio_con_el_formulario_de_reservas(string $plantilla): void
    {
        foreach ([Restaurante::factory(), Restaurante::factory()->sitioMenu()] as $fabrica) {
            $restaurante = $fabrica->create(['sitio_plantilla' => $plantilla]);

            $this->get(route('sitio.publico', ['slug' => $restaurante->slug]))
                ->assertOk()
                ->assertSee('id="form-reserva"', false);
        }
    }

    #[DataProvider('plantillasDelSitio')]
    public function test_solo_menu_redirige_el_sitio_al_menu_con_302(string $plantilla): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['sitio_plantilla' => $plantilla]);

        $this->get('/'.$restaurante->slug)
            ->assertStatus(302)
            ->assertRedirect(url('/'.$restaurante->slug.'/menu'));
    }

    public function test_solo_menu_conserva_lang_y_mesa_al_redirigir(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $this->get('/'.$restaurante->slug.'?lang=en&mesa=5')
            ->assertStatus(302)
            ->assertRedirect(url('/'.$restaurante->slug.'/menu?lang=en&mesa=5'));
    }

    public function test_la_redireccion_de_solo_menu_lleva_cache_corta_y_nunca_301(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $respuesta = $this->get('/'.$restaurante->slug)->assertStatus(302);

        $this->assertStringContainsString('max-age=30', (string) $respuesta->headers->get('Cache-Control'));
    }

    public function test_solo_menu_sigue_mostrando_el_menu(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
    }

    public function test_el_qr_de_un_restaurante_en_solo_menu_sigue_yendo_al_menu(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();
        $qr = CodigoQr::factory()->deMesa('7')->create(['restaurante_id' => $restaurante->id, 'activo' => true]);

        $this->get(route('qr.redirect', ['codigo' => $qr->codigo]))
            ->assertStatus(302)
            ->assertRedirect(url('/'.$restaurante->slug.'/menu?mesa=7'));
    }

    public function test_la_ruta_anterior_del_sitio_termina_en_el_menu_en_solo_menu(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create();

        $this->get('/s/'.$restaurante->slug)->assertStatus(301)->assertRedirect(url('/'.$restaurante->slug));
        $this->get('/'.$restaurante->slug)->assertStatus(302)->assertRedirect(url('/'.$restaurante->slug.'/menu'));
    }

    public function test_un_restaurante_suspendido_en_solo_menu_sigue_dando_404_y_no_redireccion(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->suspendido()->create();

        $this->get('/'.$restaurante->slug)->assertNotFound();
    }

    public function test_el_pedido_por_whatsapp_sigue_funcionando_en_solo_menu(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create([
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
            'zona_horaria' => 'America/Mexico_City',
        ]);
        Horario::factory()->create([
            'restaurante_id' => $restaurante->id,
            'dia_semana' => 3,
            'abre' => '00:00:00',
            'cierra' => '23:59:59',
            'cerrado' => false,
        ]);

        $this->get(route('menu.publico', ['slug' => $restaurante->slug]))
            ->assertOk()
            ->assertSee('id="floatingCartBar"', false);
    }

    public function test_la_disponibilidad_se_ofrece_en_completo_y_sitio_y_menu_pero_no_en_solo_menu(): void
    {
        foreach ([[Restaurante::factory(), true], [Restaurante::factory()->sitioMenu(), true], [Restaurante::factory()->soloMenu(), false]] as [$fabrica, $ofrece]) {
            $restaurante = $fabrica->create(['reservas_cupo' => 10, 'reservas_franja_min' => 60]);
            $this->conHorarioDelMiercoles($restaurante);

            $franjas = $this->getJson('/api/v1/sitio/'.$restaurante->slug.'/disponibilidad?fecha=2026-10-21&personas=2')
                ->assertOk()
                ->json('data.franjas');

            $this->assertSame($ofrece, $franjas !== [], 'Franjas inesperadas en '.$restaurante->plan->value);
        }
    }

    public function test_una_reserva_se_crea_en_completo_y_sitio_y_menu(): void
    {
        foreach ([Restaurante::factory(), Restaurante::factory()->sitioMenu()] as $fabrica) {
            $restaurante = $fabrica->create(['reservas_cupo' => 10, 'reservas_franja_min' => 60]);
            $this->conHorarioDelMiercoles($restaurante);
            Cache::flush();

            $this->postJson('/api/v1/sitio/'.$restaurante->slug.'/reservas', $this->datosDeReserva())->assertCreated();
        }

        $this->assertSame(2, Reserva::withoutGlobalScopes()->count());
    }

    public function test_en_solo_menu_la_reserva_se_rechaza_y_no_se_guarda_nada(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['reservas_activas' => true, 'reservas_cupo' => 10, 'reservas_franja_min' => 60]);
        $this->conHorarioDelMiercoles($restaurante);

        $this->postJson('/api/v1/sitio/'.$restaurante->slug.'/reservas', $this->datosDeReserva())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha' => 'Por ahora no recibimos reservas en línea.']);

        $this->assertSame(0, Reserva::withoutGlobalScopes()->count());
    }

    public function test_volver_a_subir_de_plan_restaura_el_sitio_las_reservas_y_la_plantilla(): void
    {
        $restaurante = Restaurante::factory()->soloMenu()->create(['sitio_plantilla' => 'c', 'reservas_activas' => true, 'reservas_cupo' => 10, 'reservas_franja_min' => 60]);
        $this->conHorarioDelMiercoles($restaurante);

        $this->get('/'.$restaurante->slug)->assertStatus(302);

        $restaurante->forceFill(['plan' => 'sitio_menu'])->save();
        Cache::flush();

        $this->get('/'.$restaurante->slug)->assertOk()->assertSee('id="form-reserva"', false);
        $this->assertSame('c', $restaurante->fresh()->sitio_plantilla);
        $this->postJson('/api/v1/sitio/'.$restaurante->slug.'/reservas', $this->datosDeReserva())->assertCreated();
    }
}
