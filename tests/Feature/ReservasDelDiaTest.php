<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\ReservasDelDia;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Reservation queries of the service panel (ReservasDelDia): the day, the upcoming ones and the figures of the day.
 *
 * Dates are compared with whereDate because Eloquent stores a Carbon assigned to `fecha` with a time part ("Y-m-d H:i:s")
 * and a string as "Y-m-d"; the tests write both ways (and by direct insert) and expect the same rows either way.
 */
class ReservasDelDiaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private ReservasDelDia $dias;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00', 'UTC'));
        $this->setUpEscenarioDePanel();
        $this->dias = app(ReservasDelDia::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reserva(Restaurante $restaurante, string $fecha, string $hora = '20:00:00', string $estado = 'confirmada', int $personas = 2): Reserva
    {
        return Reserva::factory()->create(['restaurante_id' => $restaurante->id, 'fecha' => $fecha, 'hora' => $hora, 'estado' => $estado, 'personas' => $personas]);
    }

    public function test_hoy_es_la_fecha_en_la_zona_del_restaurante(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 03:30:00', 'UTC'));

        $this->assertSame('2026-10-21', $this->dias->hoy(Restaurante::factory()->create(['zona_horaria' => 'America/Mexico_City'])));
        $this->assertSame('2026-10-22', $this->dias->hoy(Restaurante::factory()->create(['zona_horaria' => 'Pacific/Auckland'])));
    }

    public function test_el_dia_encuentra_las_reservas_guardadas_de_cualquier_forma(): void
    {
        $restaurante = Restaurante::factory()->create();

        $conTexto = $this->reserva($restaurante, '2026-10-26', '19:00:00');
        $conFecha = Reserva::create(['restaurante_id' => $restaurante->id, 'nombre' => 'Con fecha', 'personas' => 2, 'fecha' => CarbonImmutable::parse('2026-10-26'), 'hora' => '18:00', 'estado' => 'confirmada']);
        $directa = DB::table('reservas')->insertGetId(['restaurante_id' => $restaurante->id, 'nombre' => 'Directa', 'personas' => 2, 'fecha' => '2026-10-26', 'hora' => '17:00:00', 'estado' => 'confirmada', 'origen' => 'panel', 'created_at' => now(), 'updated_at' => now()]);
        $this->reserva($restaurante, '2026-10-27');

        $encontradas = $this->dias->delDia($restaurante, '2026-10-26');

        $this->assertSame([$directa, $conFecha->id, $conTexto->id], $encontradas->pluck('id')->all());
    }

    public function test_el_dia_ordena_por_hora_y_luego_por_id_y_no_mezcla_restaurantes(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        $tarde = $this->reserva($restaurante, '2026-10-21', '21:00:00');
        $primeraTemprano = $this->reserva($restaurante, '2026-10-21', '13:00:00');
        $segundaTemprano = $this->reserva($restaurante, '2026-10-21', '13:00:00');
        $this->reserva($otro, '2026-10-21', '12:00:00');

        $this->assertSame([$primeraTemprano->id, $segundaTemprano->id, $tarde->id], $this->dias->delDia($restaurante, '2026-10-21')->pluck('id')->all());
    }

    public function test_las_proximas_excluyen_ayer_y_se_ordenan_por_fecha_hora_e_id(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->reserva($restaurante, '2026-10-20');
        $manana = $this->reserva($restaurante, '2026-10-22', '13:00:00');
        $hoyTarde = $this->reserva($restaurante, '2026-10-21', '21:00:00');
        $hoyTemprano = $this->reserva($restaurante, '2026-10-21', '12:00:00');

        $ids = $this->dias->proximas($restaurante, '2026-10-21')->pluck('id')->all();

        $this->assertSame([$hoyTemprano->id, $hoyTarde->id, $manana->id], $ids);
    }

    public function test_las_proximas_tienen_un_tope(): void
    {
        $restaurante = Restaurante::factory()->create();
        Reserva::factory()->count(ReservasDelDia::MAXIMO_PROXIMAS + 1)->create(['restaurante_id' => $restaurante->id, 'fecha' => '2026-10-22']);

        $this->assertCount(ReservasDelDia::MAXIMO_PROXIMAS, $this->dias->proximas($restaurante, '2026-10-21'));
    }

    public function test_el_resumen_del_dia_no_cuenta_canceladas_otras_fechas_ni_otros_restaurantes(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        $this->reserva($restaurante, '2026-10-21', estado: 'pendiente', personas: 3);
        $this->reserva($restaurante, '2026-10-21', estado: 'confirmada', personas: 4);
        $this->reserva($restaurante, '2026-10-21', estado: 'llego', personas: 2);
        $this->reserva($restaurante, '2026-10-21', estado: 'cancelada', personas: 8);
        $this->reserva($restaurante, '2026-10-22', estado: 'pendiente', personas: 5);
        $this->reserva($restaurante, '2026-10-20', estado: 'pendiente', personas: 6);
        $this->reserva($otro, '2026-10-21', estado: 'pendiente', personas: 7);

        $this->assertSame(['reservas_hoy' => 3, 'comensales_esperados' => 9, 'por_confirmar' => 1], $this->dias->resumenDelDia($restaurante));
    }

    public function test_por_confirmar_desde_hoy_incluye_el_futuro_pero_no_el_pasado(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        $this->reserva($restaurante, '2026-10-20', estado: 'pendiente');
        $this->reserva($restaurante, '2026-10-21', estado: 'pendiente');
        $this->reserva($restaurante, '2026-11-02', estado: 'pendiente');
        $this->reserva($restaurante, '2026-11-02', estado: 'confirmada');
        $this->reserva($otro, '2026-11-02', estado: 'pendiente');

        $this->assertSame(2, $this->dias->porConfirmarDesdeHoy($restaurante));
    }

    public function test_las_consultas_filtran_por_restaurante_aun_sin_contexto_activo(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        $this->reserva($restaurante, '2026-10-21');
        $this->reserva($otro, '2026-10-21');
        $this->reserva($otro, '2026-10-21');

        $this->assertNull(RestauranteScope::getRestauranteId());
        $this->assertCount(1, $this->dias->delDia($restaurante, '2026-10-21'));
        $this->assertCount(1, $this->dias->proximas($restaurante, '2026-10-21'));
        $this->assertSame(1, $this->dias->resumenDelDia($restaurante)['reservas_hoy']);
    }
}
